<?php

namespace App\Services;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\Reader;
use App\Models\Stage;
use App\Models\Tick;
use App\Models\Version;
use Illuminate\Support\Collection;

/**
 * Where a reader stands on one guide, for their dashboard: every line, and the videos, links
 * (a site outside the guide — often where a lesson lives when there is no video) and practices
 * among them, done of total; stages finished; when they last ticked anything; and the line to
 * continue with — the first not ticked, in the guide's order of stage, card and line.
 *
 * Counted against the version they read — the released one — by each line's key, as the guide
 * page counts it, so a tick on a line a later version removed is not counted.
 *
 * Also the editor's readers list, one call per reader: a version's lines are loaded once.
 */
class GuideProgressSummary
{
    /** @var array<int, Collection<int, array{item: Item, stage: int, stage_title: string}>> */
    private array $linesOf = [];

    /** @return array{done: int, total: int, videos: array{done: int, total: int}, links: array{done: int, total: int}, practices: array{done: int, total: int}, stages: array{done: int, total: int}, last_ticked_at: ?string, next: ?array{key: string, title: string, stage: int, stage_title: string, minutes: ?int}} */
    public function for(Reader $reader, Version $version): array
    {
        $lines = $this->linesOf[$version->id] ??= $this->lines($version);
        $items = $lines->pluck('item');

        $ticks = Tick::query()->where('user_id', $reader->user_id)->where('guide_id', $reader->guide_id);
        $done = array_flip((clone $ticks)->pluck('item_key')->all());

        $isDone = fn (Item $item) => isset($done[$item->key]);
        $tally = fn ($lines) => ['done' => $lines->filter($isDone)->count(), 'total' => $lines->count()];

        $stages = $lines->groupBy('stage')->map->pluck('item');
        $next = $lines->first(fn (array $line) => ! $isDone($line['item']));

        return [
            ...$tally($items),
            'videos' => $tally($items->filter(fn (Item $item) => in_array($item->kind, [ItemKind::YOUTUBE_VIDEO, ItemKind::YOUTUBE_PLAYLIST], true))),
            'links' => $tally($items->filter(fn (Item $item) => $item->kind === ItemKind::EXTERNAL_SITE)),
            'practices' => $tally($items->filter(fn (Item $item) => $item->kind === ItemKind::PRACTICE)),
            'stages' => [
                'done' => $stages->filter(fn ($lines) => $lines->every($isDone))->count(),
                'total' => $stages->count(),
            ],
            'last_ticked_at' => (clone $ticks)->max('created_at'),
            'next' => $next ? [
                'key' => $next['item']->key,
                'title' => $next['item']->title,
                'stage' => $next['stage'],
                'stage_title' => $next['stage_title'],
                'minutes' => $next['item']->minutes(),
            ] : null,
        ];
    }

    /**
     * A version's lines in reading order, each with its stage's number — counted from one, as the
     * page numbers them — and title.
     *
     * @return Collection<int, array{item: Item, stage: int, stage_title: string}>
     */
    private function lines(Version $version): Collection
    {
        return $version->stages()
            ->with(['cards:id,stage_id,position', 'cards.items:id,card_id,key,kind,title,position,clip_start,clip_end,brief'])
            ->get(['id', 'version_id', 'title', 'position'])
            ->values()
            ->flatMap(fn (Stage $stage, int $index) => $stage->cards->flatMap->items
                ->map(fn (Item $item) => ['item' => $item, 'stage' => $index + 1, 'stage_title' => $stage->title]));
    }
}
