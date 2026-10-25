<?php

namespace App\Services;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\Reader;
use App\Models\Tick;
use App\Models\Version;
use Illuminate\Support\Collection;

/**
 * Where a reader stands on one guide, for their dashboard: every line, and the videos, links
 * (a site outside the guide — often where a lesson lives when there is no video) and practices
 * among them, done of total; stages finished; and when they last ticked anything.
 *
 * Counted against the version they read — the released one — by each line's key, as the guide
 * page counts it, so a tick on a line a later version removed is not counted.
 *
 * Also the editor's readers list, one call per reader: a version's lines are loaded once.
 */
class GuideProgressSummary
{
    /** @var array<int, Collection<int, Item>> */
    private array $itemsOf = [];

    /** @return array{done: int, total: int, videos: array{done: int, total: int}, links: array{done: int, total: int}, practices: array{done: int, total: int}, stages: array{done: int, total: int}, last_ticked_at: ?string} */
    public function for(Reader $reader, Version $version): array
    {
        $items = $this->itemsOf[$version->id] ??= Item::query()
            ->where('version_id', $version->id)
            ->with('card:id,stage_id')
            ->get(['id', 'key', 'kind', 'card_id']);

        $ticks = Tick::query()->where('user_id', $reader->user_id)->where('guide_id', $reader->guide_id);
        $done = array_flip((clone $ticks)->pluck('item_key')->all());

        $isDone = fn (Item $item) => isset($done[$item->key]);
        $tally = fn ($lines) => ['done' => $lines->filter($isDone)->count(), 'total' => $lines->count()];

        $stages = $items->groupBy(fn (Item $item) => $item->card->stage_id);

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
        ];
    }
}
