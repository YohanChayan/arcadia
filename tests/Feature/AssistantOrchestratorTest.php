<?php

namespace Tests\Feature;

use App\Assistant\Agents\CachedSpeaker;
use App\Assistant\Agents\Planner;
use App\Assistant\Agents\Speaker;
use App\Assistant\Contracts\Channel;
use App\Assistant\Models\Conversation;
use App\Assistant\Models\Turn;
use App\Assistant\Orchestrator;
use App\Assistant\Pricing;
use App\Assistant\TurnMetrics;
use App\Assistant\TurnStatus;
use App\Enums\Level;
use App\Knowledge\ChunkType;
use App\Knowledge\Contracts\Retriever;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\TextUsage;
use RuntimeException;
use Tests\TestCase;

/**
 * The orchestrator owns a turn and knows no channel: driven here by a channel that only records,
 * it streams the reply, records what the turn decided, offers only a guide its passages name,
 * and counts a conversion only for a guide it had already offered.
 */
class AssistantOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private Guide $excel;

    private Guide $speaking;

    private RecordingChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assistant.cache_instructions' => false, 'assistant.prices' => []]);

        $this->excel = Guide::create(['locale' => 'es', 'title' => 'Excel desde cero', 'level' => Level::BASIC]);
        $this->speaking = Guide::create(['locale' => 'es', 'title' => 'Hablar en público', 'level' => Level::BASIC]);
        $this->channel = new RecordingChannel;

        $this->retrieves([$this->excel]);
    }

    public function test_a_turn_streams_its_reply_to_the_channel_and_is_recorded(): void
    {
        Planner::fake([$this->plan(engagement: 64)]);
        Speaker::fake(['¡Qué bueno que quieras aprender Excel!']);

        $turn = $this->orchestrator()->reply($this->conversation(), 'Quiero aprender Excel', $this->channel);

        $this->assertSame('started', $this->channel->events[0]);
        $this->assertSame('completed', last($this->channel->events));
        $this->assertSame('¡Qué bueno que quieras aprender Excel!', $this->channel->reply());
        $this->assertSame(64, $this->channel->metrics->engagement);
        $this->assertSame([$this->excel->id], array_column($this->channel->metrics->toArray()['passages'], 'guide_id'));

        $turn->refresh();
        $this->assertSame(TurnStatus::DONE, $turn->status);
        $this->assertSame('¡Qué bueno que quieras aprender Excel!', $turn->reply);
        $this->assertSame(64, $turn->engagement);
    }

    public function test_the_turn_context_travels_with_the_message_and_never_in_the_instructions(): void
    {
        Planner::fake([$this->plan()]);
        Speaker::fake(['Hola']);

        $this->orchestrator()->reply($this->conversation(), 'Quiero aprender Excel', $this->channel);

        Speaker::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('<contexto>')
            && $prompt->contains('Excel desde cero')
            && ! str_contains((string) $prompt->agent->instructions(), 'Excel desde cero'));
    }

    public function test_later_turns_are_shown_the_completed_ones_as_history(): void
    {
        $conversation = $this->conversation();
        Planner::fake([$this->plan(), $this->plan()]);
        Speaker::fake(['Primera respuesta', 'Segunda respuesta']);

        $this->orchestrator()->reply($conversation, 'Hola', $this->channel);
        $this->orchestrator()->reply($conversation, 'Quiero aprender Excel', new RecordingChannel);

        Speaker::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Quiero aprender Excel')
            && $prompt->agent instanceof Speaker
            && collect($prompt->agent->messages())->pluck('content')->all() === ['Hola', 'Primera respuesta']);
    }

    public function test_only_a_guide_named_by_the_passages_can_be_offered(): void
    {
        Planner::fake([$this->plan(offer: $this->speaking->id), $this->plan(offer: $this->excel->id)]);
        Speaker::fake(['Uno', 'Dos']);
        $conversation = $this->conversation();

        $notRetrieved = $this->orchestrator()->reply($conversation, 'Algo', $this->channel);
        $retrieved = $this->orchestrator()->reply($conversation, 'Excel', new RecordingChannel);

        $this->assertNull($notRetrieved->refresh()->offered_guide_id);
        $this->assertSame($this->excel->id, $retrieved->refresh()->offered_guide_id);
    }

    public function test_accepting_an_offered_guide_records_one_conversion_with_its_guide(): void
    {
        Planner::fake([
            $this->plan(offer: $this->excel->id),
            $this->plan(accepted: $this->excel->id),
            $this->plan(accepted: $this->excel->id),
        ]);
        Speaker::fake(['Te recomiendo Excel desde cero', '¡Genial!', '¡Sigue así!']);
        $conversation = $this->conversation();
        $accepting = new RecordingChannel;
        $acceptingAgain = new RecordingChannel;

        $this->orchestrator()->reply($conversation, 'Quiero aprender Excel', $this->channel);
        $this->orchestrator()->reply($conversation, 'Sí, la quiero', $accepting);
        $this->orchestrator()->reply($conversation, 'Sí, de verdad', $acceptingAgain);

        $this->assertSame(['guide_id' => $this->excel->id, 'guide_title' => 'Excel desde cero'], $accepting->metrics->conversion);
        $this->assertNull($acceptingAgain->metrics->conversion);
        $this->assertSame([$this->excel->id], $conversation->conversions()->pluck('guide_id')->all());
    }

    public function test_enthusiasm_for_a_guide_never_offered_is_not_a_conversion(): void
    {
        Planner::fake([$this->plan(accepted: $this->excel->id)]);
        Speaker::fake(['¡Genial!']);
        $conversation = $this->conversation();

        $this->orchestrator()->reply($conversation, 'Quiero Excel desde cero', $this->channel);

        $this->assertNull($this->channel->metrics->conversion);
        $this->assertSame(0, $conversation->conversions()->count());
    }

    public function test_a_failed_turn_is_marked_reported_to_the_channel_and_left_out_of_the_history(): void
    {
        Planner::fake([fn () => throw new RuntimeException('Provider down')]);
        Speaker::fake();
        $conversation = $this->conversation();

        $turn = $this->orchestrator()->reply($conversation, 'Hola', $this->channel);

        $this->assertSame(TurnStatus::FAILED, $turn->refresh()->status);
        $this->assertSame(['started', 'failed'], $this->channel->events);
        $this->assertSame(__('arcadia.assistant.failed', [], 'es'), $this->channel->failure);
        $this->assertCount(0, $conversation->history(20));
        Speaker::assertNeverPrompted();
    }

    public function test_cost_is_reported_only_for_a_priced_model(): void
    {
        Planner::fake([$this->plan(), $this->plan()]);
        Speaker::fake(['Uno', 'Dos']);
        $conversation = $this->conversation();
        $priced = new RecordingChannel;

        $this->orchestrator()->reply($conversation, 'Hola', $this->channel);
        config(['assistant.prices' => [
            config('assistant.models.planner') => ['input' => 0.1, 'cached_input' => 0.025, 'output' => 0.4],
            config('assistant.models.speaker') => ['input' => 0.1, 'cached_input' => 0.025, 'output' => 0.4],
        ]]);
        $this->orchestrator()->reply($conversation, 'Hola', $priced);

        $this->assertNull($this->channel->metrics->cost);
        $this->assertSame(0.0, $priced->metrics->cost);
    }

    public function test_cached_input_is_priced_at_its_own_rate(): void
    {
        config(['assistant.prices' => ['gemini-3.1-flash-lite' => ['input' => 0.1, 'cached_input' => 0.025, 'output' => 0.4]]]);

        $cost = app(Pricing::class)->cost('gemini-3.1-flash-lite', new TextUsage(inputTokens: 1000, outputTokens: 500, cacheReadInputTokens: 400));

        // 600 uncached × 0.1 + 400 cached × 0.025 + 500 out × 0.4, per million.
        $this->assertEqualsWithDelta(0.00027, $cost, 1e-12);
    }

    public function test_the_cache_switch_chooses_the_speaker_marked_for_caching(): void
    {
        config(['assistant.cache_instructions' => true]);
        Planner::fake([$this->plan()]);
        CachedSpeaker::fake(['Hola']);

        $this->orchestrator()->reply($this->conversation(), 'Hola', $this->channel);

        CachedSpeaker::assertPromptedTimes(1);
    }

    public function test_a_conversation_starts_only_in_a_language_the_assistant_speaks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->orchestrator()->start('test', 'fr');
    }

    private function orchestrator(): Orchestrator
    {
        return app(Orchestrator::class);
    }

    private function conversation(): Conversation
    {
        return $this->orchestrator()->start($this->channel->name(), 'es');
    }

    /**
     * @return array{engagement: int, accepted_guide_id: int|null, offer_guide_id: int|null}
     */
    private function plan(int $engagement = 50, ?int $accepted = null, ?int $offer = null): array
    {
        return ['engagement' => $engagement, 'accepted_guide_id' => $accepted, 'offer_guide_id' => $offer];
    }

    /**
     * @param  list<Guide>  $guides
     */
    private function retrieves(array $guides): void
    {
        $this->app->instance(Retriever::class, new class($guides) implements Retriever
        {
            /** @param  list<Guide>  $guides */
            public function __construct(private array $guides) {}

            public function retrieve(RetrievalQuery $query): RetrievedPassages
            {
                return RetrievedPassages::make(array_map(
                    fn (Guide $guide): RetrievedPassage => new RetrievedPassage("{$guide->title}. Nivel básico.", 0.8, 'test', $guide->id, $guide->title, ChunkType::GUIDE, $query->locale),
                    $this->guides,
                ));
            }
        });
    }
}

/** A channel that only records what the orchestrator told it, in order. */
class RecordingChannel implements Channel
{
    /** @var list<string> */
    public array $events = [];

    /** @var list<string> */
    public array $deltas = [];

    public ?TurnMetrics $metrics = null;

    public ?string $failure = null;

    public function name(): string
    {
        return 'test';
    }

    public function turnStarted(Turn $turn): void
    {
        $this->events[] = 'started';
    }

    public function replyDelta(string $text): void
    {
        $this->events[] = 'delta';
        $this->deltas[] = $text;
    }

    public function turnCompleted(TurnMetrics $metrics): void
    {
        $this->events[] = 'completed';
        $this->metrics = $metrics;
    }

    public function turnFailed(string $message): void
    {
        $this->events[] = 'failed';
        $this->failure = $message;
    }

    public function reply(): string
    {
        return implode('', $this->deltas);
    }
}
