<?php

namespace App\Actions\Guides;

use App\Actions\Readers\TellReaders;
use App\Enums\GuidePublishing;
use App\Enums\GuideStatus;
use App\Models\Guide;

/**
 * A guide made readable from the guides list: its pending draft released when it holds content,
 * its status set to published, and whoever was waiting told.
 *
 * Unlike releasing from the content screen, this always publishes — the owner asked for exactly
 * that. A guide with nothing released and nothing in its draft is left alone: publishing it
 * would put up a link no reader could open.
 */
class PublishGuide
{
    public function __construct(private TellReaders $tell) {}

    /** What `handle()` would do, without doing it. */
    public function assess(Guide $guide): GuidePublishing
    {
        $latest = $guide->latestVersion();
        $pendingContent = ! $latest->isReleased() && $latest->items()->exists();

        if ($pendingContent) {
            return GuidePublishing::RELEASES_DRAFT;
        }

        if ($guide->releasedVersion() === null) {
            return GuidePublishing::NO_CONTENT;
        }

        return $guide->status === GuideStatus::PUBLISHED ? GuidePublishing::READY : GuidePublishing::PUBLISHES;
    }

    public function handle(Guide $guide): GuidePublishing
    {
        $outcome = $this->assess($guide);

        if ($outcome === GuidePublishing::NO_CONTENT || $outcome === GuidePublishing::READY) {
            return $outcome;
        }

        if ($outcome === GuidePublishing::RELEASES_DRAFT) {
            $guide->latestVersion()->release();
        }

        $guide->update(['status' => GuideStatus::PUBLISHED]);
        $this->tell->waiting($guide);

        return $outcome;
    }
}
