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
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Ai\Responses\Data\TextUsage;
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
            $passages = $this->retriever->retrieve(new RetrievalQuery(
                $message,
                $conversation->locale,
                limit: config('assistant.passages'),
            ));
            $guideTitles = $this->guideTitles($passages, $offeredGuideIds);
            $offeredGuides = collect($offeredGuideIds)->mapWithKeys(fn (int $id): array => [$id => $guideTitles[$id] ?? (string) $id]);

            $planned = Planner::make(locale: $conversation->locale, history: $history)->prompt(
                $this->prompts->plannerTurn(
                    $conversation->locale,
                    $channel->name(),
                    $previousEngagement,
                    $offeredGuides,
                    $passages,
                    $message,
                ),
            );

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
            $stream = $speaker::make(locale: $conversation->locale, history: $history)->stream(
                $this->prompts->speakerTurn(
                    $conversation->locale,
                    $channel->name(),
                    $plan->engagement,
                    $offeredGuides,
                    $passages,
                    $plan->acceptedGuideId !== null ? $guideTitles[$plan->acceptedGuideId] ?? null : null,
                    $plan->offerGuideId !== null ? $guideTitles[$plan->offerGuideId] ?? null : null,
                    $message,
                ),
            );

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $channel->replyDelta($event->delta);
                }
            }

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
