<?php

namespace App\Actions\Guides;

use App\Enums\GuideListing;
use App\Models\Guide;

/**
 * A guide put in the catalogue from the guides list: opened for enrollment and kept in the
 * catalogue, the two switches `Guide::scopeListed()` reads besides being readable (ADR 0024).
 *
 * Opening it too is the point — the switch means nothing on a closed guide, and the catalogue
 * lists only what can be asked for. A guide that is not readable yet is marked all the same, and
 * appears once it is published. Saved through the model, so each change reindexes the guide.
 */
class ListGuide
{
    /** What `handle()` would do, without doing it. */
    public function assess(Guide $guide): GuideListing
    {
        if (! $guide->isReadable()) {
            return GuideListing::AWAITS_PUBLISHING;
        }

        return $guide->open_for_enrollment && $guide->in_catalogue ? GuideListing::READY : GuideListing::LISTS;
    }

    public function handle(Guide $guide): GuideListing
    {
        $outcome = $this->assess($guide);

        $guide->update(['open_for_enrollment' => true, 'in_catalogue' => true]);

        return $outcome;
    }
}
