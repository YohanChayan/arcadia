<?php

namespace App\Services\Site;

use App\Assistant\Models\Conversation;
use App\Assistant\Models\Conversion;
use App\Assistant\Models\Turn;
use App\Assistant\TurnStatus;
use App\Enums\QueueName;
use App\Knowledge\KnowledgeChunk;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What the site's agents have been doing, as counts and metrics: the sales assistant's
 * conversations and turns, and the knowledge index it retrieves from. Never what anyone said —
 * no visitor message, no reply, no passage text.
 *
 * Read from the assistant's own tables and the `vectors` connection, plus the jobs and
 * failed-jobs tables for the index's queue. The provider and models are read from config, so the
 * screen names whatever is configured, not what was true when this was written.
 *
 * A turn records no reason when it fails; that is in the log, at the turn's time.
 */
class AgentActivity
{
    /** A turn still `replying` this long after it was last touched crashed mid-stream. */
    public const STUCK_MINUTES = 5;

    /**
     * The sales assistant inside a window. `stuck` is every turn left replying, whenever it
     * started: like a failed job, it stays a problem until someone looks.
     *
     * @return array<string, mixed>
     */
    public function assistant(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $provider = (string) config('assistant.provider');
        $keyPresent = filled(config("ai.providers.{$provider}.key"));

        $turns = $this->within(Turn::query(), $start, $end);
        $byStatus = (clone $turns)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $failed = (int) ($byStatus[TurnStatus::FAILED->value] ?? 0);

        $stuck = Turn::query()
            ->where('status', TurnStatus::REPLYING)
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->count();

        $priced = (clone $turns)->whereNotNull('cost');

        return [
            'provider' => $provider,
            'models' => config('assistant.models'),
            'key_present' => $keyPresent,
            'conversations' => [
                'total' => $this->within(Conversation::query(), $start, $end)->count(),
                'by_channel' => $this->within(Conversation::query(), $start, $end)->toBase()
                    ->selectRaw('channel, count(*) as total')->groupBy('channel')->orderBy('channel')
                    ->pluck('total', 'channel')->map(fn (mixed $total): int => (int) $total)->all(),
            ],
            'turns' => [
                'total' => (int) $byStatus->sum(),
                'done' => (int) ($byStatus[TurnStatus::DONE->value] ?? 0),
                'failed' => $failed,
                'replying' => (int) ($byStatus[TurnStatus::REPLYING->value] ?? 0),
                'stuck' => $stuck,
                'stuck_minutes' => self::STUCK_MINUTES,
            ],
            'by_model' => (clone $turns)->toBase()->whereNotNull('model')
                ->selectRaw('model, count(*) as total')->groupBy('model')->orderByDesc('total')
                ->pluck('total', 'model')->map(fn (mixed $total): int => (int) $total)->all(),
            'usage' => $this->usage(clone $turns),
            // Null, not zero, while no turn in the window has a price: `assistant.prices` is empty.
            'cost' => (clone $priced)->exists() ? round((float) $priced->sum('cost'), 6) : null,
            'engagement' => ($average = (clone $turns)->avg('engagement')) !== null ? (int) round((float) $average) : null,
            'conversions' => $this->within(Conversion::query(), $start, $end)->count(),
            'level' => match (true) {
                ! $keyPresent => 'error',
                $stuck > 0, $failed > 0 => 'warn',
                default => 'ok',
            },
        ];
    }

    /**
     * The knowledge index: how many passages it holds and with which embedding model, and whether
     * its queue is keeping up. Not windowed — the index is a state, not a stream of events.
     *
     * @return array<string, mixed>
     */
    public function knowledge(): array
    {
        $queue = QueueName::KNOWLEDGE->value;
        $pending = DB::table('jobs')->where('queue', $queue)->count();
        $failed = DB::table('failed_jobs')->where('queue', $queue)->count();
        $model = (string) config('knowledge.embeddings.model');

        $index = $this->index();

        return [
            'reachable' => $index !== null,
            'chunks' => $index['chunks'] ?? null,
            'listed' => $index['listed'] ?? null,
            'by_locale' => $index['by_locale'] ?? [],
            'last_indexed' => $index['last_indexed'] ?? null,
            'embedding_model' => $model,
            // Passages embedded by another model cannot be compared with today's questions.
            'other_models' => array_values(array_diff($index['models'] ?? [], [$model])),
            'queue' => $queue,
            'pending' => $pending,
            'failed' => $failed,
            'level' => match (true) {
                $index === null, $failed > 0 => 'error',
                $index['chunks'] === 0, $index['models'] !== [] && $index['models'] !== [$model] => 'warn',
                default => 'ok',
            },
        ];
    }

    /**
     * Tokens across both calls of every turn, the planner's and the speaker's, and how long a
     * completed turn took. Summed here, not in SQL: `usage` is JSON, and the test database is not
     * the production one.
     *
     * @param  Builder<Turn>  $turns
     * @return array{input_tokens: int, cached_input_tokens: int, output_tokens: int, average_seconds: ?float}
     */
    private function usage(Builder $turns): array
    {
        $totals = ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0];
        $seconds = [];

        foreach ($turns->toBase()->select(['status', 'usage', 'created_at', 'updated_at'])->cursor() as $turn) {
            foreach ((array) json_decode((string) $turn->usage, true) as $call) {
                $totals['input_tokens'] += (int) data_get($call, 'input_tokens', 0);
                $totals['cached_input_tokens'] += (int) data_get($call, 'cache_read_input_tokens', 0);
                $totals['output_tokens'] += (int) data_get($call, 'output_tokens', 0);
            }

            if ($turn->status === TurnStatus::DONE->value) {
                $seconds[] = CarbonImmutable::parse($turn->created_at)->diffInSeconds(CarbonImmutable::parse($turn->updated_at), true);
            }
        }

        return $totals + ['average_seconds' => $seconds ? round(array_sum($seconds) / count($seconds), 1) : null];
    }

    /**
     * The index's counts, or null when the `vectors` connection cannot be reached.
     *
     * @return array{chunks: int, listed: int, by_locale: array<string, int>, last_indexed: ?string, models: list<string>}|null
     */
    private function index(): ?array
    {
        try {
            $chunks = KnowledgeChunk::query();
            $latest = (clone $chunks)->max('updated_at');

            return [
                'chunks' => (clone $chunks)->count(),
                'listed' => (clone $chunks)->where('listed', true)->count(),
                'by_locale' => (clone $chunks)->toBase()->selectRaw('locale, count(*) as total')->groupBy('locale')->orderBy('locale')
                    ->pluck('total', 'locale')->map(fn (mixed $total): int => (int) $total)->all(),
                'last_indexed' => $latest ? CarbonImmutable::parse($latest)->toAtomString() : null,
                'models' => (clone $chunks)->distinct()->orderBy('embedding_model')->pluck('embedding_model')->all(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function within(Builder $query, ?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $query
            ->when($start, fn (Builder $query) => $query->where('created_at', '>=', $start))
            ->when($end, fn (Builder $query) => $query->where('created_at', '<=', $end));
    }
}
