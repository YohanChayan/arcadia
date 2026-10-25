<?php

namespace App\Actions;

use App\Models\Guide;
use App\Models\Version;
use Illuminate\Support\Facades\DB;

/**
 * The next version of a guide, as a draft copy of the latest one — to add, modify, remove or
 * reorder freely before it is released.
 *
 * The copy keeps every item's key, so a reader moved to the new version keeps every checkmark
 * on a line that survives. If the latest version is still a draft, that draft is returned: there
 * is only ever one version being edited.
 */
class DraftNextVersion
{
    public function handle(Guide $guide): Version
    {
        $latest = $guide->latestVersion();

        if (! $latest->isReleased()) {
            return $latest;
        }

        return DB::transaction(function () use ($guide, $latest) {
            $draft = $guide->versions()->create(['number' => $latest->number + 1]);

            foreach ($latest->sections as $section) {
                $draft->sections()->save($section->replicate()->setRelations([]));
            }

            foreach ($latest->stages()->with('cards.items')->get() as $stage) {
                $stageCopy = $draft->stages()->save($stage->replicate()->setRelations([]));

                foreach ($stage->cards as $card) {
                    // `replicate()` carries every node's key across; a card and an item also carry
                    // their own `version_id`, which must become the draft's.
                    $cardCopy = $card->replicate()->setRelations([]);
                    $cardCopy->version_id = $draft->id;
                    $stageCopy->cards()->save($cardCopy);

                    foreach ($card->items as $item) {
                        $itemCopy = $item->replicate()->setRelations([]);
                        $itemCopy->version_id = $draft->id;
                        $cardCopy->items()->save($itemCopy);
                    }
                }
            }

            return $draft;
        });
    }
}
