<?php

namespace App\Assistant;

use App\Assistant\Agents\CachedSpeaker;
use App\Assistant\Agents\Planner;
use App\Assistant\Agents\Speaker;
use App\Assistant\Contracts\Channel;
use App\Assistant\Models\Conversation;
use App\Assistant\Models\Turn;
use App\Knowledge\Contracts\Retriever;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;
use App\Models\Guide;
use App\Models\User;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use LogicException;
use Throwable;

/**
 * Owns one turn of a conversation with the sales assistant, and knows no channel.
 *
 * A turn: retrieve the passages that fit the visitor's message, ask the planner what it reads in
 * it (engagement, an acceptance, the guide to offer), record any conversion, then stream the
 * speaker's reply to the channel and record what the turn decided and cost. Two calls, because
 * `laravel/ai` cannot stream structured output. A turn that fails is kept, marked, and left out
 * of the history.
 */
class Orchestrator
{
    /** How long to wait before the one retry a transient provider error gets. */
    private const RETRY_AFTER_MS = 1000;

    public function __construct(
        private Retriever $retriever,
        private Pricing $pricing,
        private Prompts $prompts,
    ) {}

    /** A new conversation, on `$channel`'s name, in a language the assistant speaks. */
    public function start(string $channel, string $locale, ?User $user = null, ?string $participant = null): Conversation
    {
        if (! in_array($locale, config('assistant.locales'), true)) {
            throw new InvalidArgumentException("The assistant does not speak [{$locale}].");
        }

        return Conversation::create([
            'channel' => $channel,
            'locale' => $locale,
            'user_id' => $user?->id,
            'participant' => $participant,
        ]);
    }

    public function reply(Conversation $conversation, string $message, Channel $channel): Turn
    {
        $history = $conversation->history(config('assistant.history_turns'));
        $offeredGuideIds = $conversation->offeredGuideIds();
        $previousEngagement = $history->last()?->engagement;

        $turn = $conversation->turns()->create([
            'status' => TurnStatus::REPLYING,
            'visitor_message' => $message,
        ]);

        $channel->turnStarted($turn);

        try {
            $passages = $this->passages($message, $conversation->locale);
            $guideTitles = $this->guideTitles($passages, $offeredGuideIds);
            $offeredGuides = collect($offeredGuideIds)->mapWithKeys(fn (int $id): array => [$id => $guideTitles[$id] ?? (string) $id]);

            $planned = $this->retryingOnce(fn (): mixed => Planner::make(locale: $conversation->locale, history: $history)->prompt(
                $this->prompts->plannerTurn(
                    $conversation->locale,
                    $channel->name(),
                    $previousEngagement,
                    $offeredGuides,
                    $passages,
                    $message,
                ),
            ));

            // A planner answers in structure; anything else fails the turn here, not further on.
            if (! $planned instanceof StructuredAgentResponse) {
                throw new LogicException('The planner did not answer in structure.');
            }

            $plan = TurnPlan::fromPlanner(
                $planned->structured,
                $offeredGuideIds,
                $passages->toBase()->map(fn (RetrievedPassage $passage): int => $passage->guideId)->unique()->values()->all(),
            );

            // Accepting the same guide again is not a second conversion, and is not reported again.
            $convertedGuideId = $plan->acceptedGuideId !== null && $conversation->conversions()->firstOrCreate(
                ['guide_id' => $plan->acceptedGuideId],
                ['turn_id' => $turn->id],
            )->wasRecentlyCreated ? $plan->acceptedGuideId : null;

            $speaker = config('assistant.cache_instructions') ? CachedSpeaker::class : Speaker::class;
            $speakerPrompt = $this->prompts->speakerTurn(
                $conversation->locale,
                $channel->name(),
                $plan->engagement,
                $offeredGuides,
                $passages,
                $plan->acceptedGuideId !== null ? $guideTitles[$plan->acceptedGuideId] ?? null : null,
                $plan->offerGuideId !== null ? $guideTitles[$plan->offerGuideId] ?? null : null,
                $message,
            );

            $stream = $this->speak($speaker::make(locale: $conversation->locale, history: $history), $speakerPrompt, $channel);

            $plannerUsage = $planned->usage;
            $speakerUsage = $stream->usage ?? new TextUsage;
            $plannerModel = config('assistant.models.planner');
            $speakerModel = config('assistant.models.speaker');
            $plannerCost = $this->pricing->cost($plannerModel, $plannerUsage);
            $speakerCost = $this->pricing->cost($speakerModel, $speakerUsage);
            $cost = $plannerCost === null || $speakerCost === null ? null : $plannerCost + $speakerCost;

            $turn->update([
                'status' => TurnStatus::DONE,
                'reply' => (string) $stream->text,
                'engagement' => $plan->engagement,
                'offered_guide_id' => $plan->offerGuideId,
                'passages' => TurnMetrics::passagesToArray($passages),
                'usage' => ['planner' => $plannerUsage->toArray(), 'speaker' => $speakerUsage->toArray()],
                'cost' => $cost,
                'model' => $speakerModel,
            ]);

            $channel->turnCompleted(new TurnMetrics(
                turnId: $turn->id,
                passages: $passages,
                engagement: $plan->engagement,
                usage: $plannerUsage->add($speakerUsage),
                cost: $cost,
                conversion: $convertedGuideId !== null
                    ? ['guide_id' => $convertedGuideId, 'guide_title' => $guideTitles[$convertedGuideId] ?? '']
                    : null,
            ));
        } catch (Throwable $exception) {
            report($exception);

            $turn->update(['status' => TurnStatus::FAILED]);
            $channel->turnFailed(__('arcadia.assistant.failed', [], $conversation->locale));
        }

        return $turn;
    }

    /**
     * Streams the speaker's reply to the channel. Retried once for a transient provider error, but
     * only while nothing has reached the visitor, so no part of a reply is shown twice.
     */
    private function speak(Speaker $speaker, string $prompt, Channel $channel): StreamableAgentResponse
    {
        for ($attempt = 1; ; $attempt++) {
            $hasStreamed = false;

            try {
                $stream = $speaker->stream($prompt);

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        $hasStreamed = true;
                        $channel->replyDelta($event->delta);
                    }
                }

                return $stream;
            } catch (Throwable $exception) {
                if ($hasStreamed || $attempt > 1 || ! $this->isTransient($exception)) {
                    throw $exception;
                }

                Sleep::usleep(self::RETRY_AFTER_MS * 1000);
            }
        }
    }

    /**
     * Runs a model call, once more if the provider failed it transiently.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function retryingOnce(Closure $call): mixed
    {
        return retry(2, $call, self::RETRY_AFTER_MS, fn (Throwable $exception): bool => $this->isTransient($exception));
    }

    /**
     * A provider error worth one more try: rate limits, overload, a dropped connection — and
     * Gemini's 403 `PERMISSION_DENIED`, which it returns now and then for a call that succeeds
     * when repeated.
     */
    private function isTransient(Throwable $exception): bool
    {
        return $exception instanceof RateLimitedException
            || $exception instanceof ProviderOverloadedException
            || $exception instanceof ProviderConnectionException
            || ($exception instanceof RequestException && $exception->response->status() === 403);
    }

    /**
     * The passages that fit the visitor's message closely enough to be offered from. A retrieval
     * that fails — the embeddings provider refusing a call now and then — is reported and leaves
     * the turn without passages, so the assistant replies that no guide fits rather than failing.
     */
    private function passages(string $message, string $locale): RetrievedPassages
    {
        try {
            $passages = $this->retriever->retrieve(new RetrievalQuery($message, $locale, limit: config('assistant.passages')));
        } catch (Throwable $exception) {
            report($exception);

            return new RetrievedPassages;
        }

        return $passages->filter(fn (RetrievedPassage $passage): bool => $passage->score >= config('assistant.min_score'))->values();
    }

    /**
     * The title of every guide this turn may name: those in its passages, and those offered
     * earlier, which this turn's passages may no longer include.
     *
     * @param  array<int, int>  $offeredGuideIds
     * @return array<int, string>
     */
    private function guideTitles(RetrievedPassages $passages, array $offeredGuideIds): array
    {
        $fromPassages = $passages->pluck('guideTitle', 'guideId')->all();

        /** @var Collection<int, string> $offered */
        $offered = Guide::query()
            ->whereIn('id', array_diff($offeredGuideIds, array_keys($fromPassages)))
            ->pluck('title', 'id');

        return $fromPassages + $offered->all();
    }
}
