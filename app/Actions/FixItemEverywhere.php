<?php

namespace App\Actions;

use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Repair one line of a guide in every version that carries it — the video was taken down, the
 * site moved. Matched by key, so it reaches the frozen, released copies a buyer already holds,
 * which is the one edit a released version accepts.
 *
 * Only what a repair needs can change: where it points, what it is called, its hint and body,
 * whether it is still available, and the clip it plays. Never its key, kind or place — a
 * different line is a new version's job.
 *
 * A clip belongs to the video, not the lesson: a repair that changes the link clears it in every
 * copy, unless the same repair sets a new one (`Item::booted()`).
 */
class FixItemEverywhere
{
    public const REPAIRABLE = ['url', 'title', 'hint', 'body', 'is_available', ...Item::CLIP_FIELDS];

    /**
     * @param  array<string, mixed>  $repair  any of `REPAIRABLE`; anything else is ignored
     * @return int how many copies were repaired
     */
    public function handle(Guide $guide, string $key, array $repair): int
    {
        $repair = Arr::only($repair, self::REPAIRABLE);

        $copies = Item::where('key', $key)
            ->whereIn('version_id', $guide->versions()->select('id'))
            ->get();

        Version::whileFixing(fn () => DB::transaction(
            fn () => $copies->each(fn (Item $item) => $item->update($repair))
        ));

        return $copies->count();
    }
}
