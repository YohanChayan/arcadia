<?php

namespace App\Services;

use App\Enums\ActivityFamily;
use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\Stage;
use App\Models\Version;

/**
 * The part of a guide an activity prompt works from, put in its `{{guide_content}}` by the server
 * (`PromptRenderer::CONTENT`): too long for any form field, and never the owner's to edit. JSON,
 * with each node's `key` exactly as the answer must give it back.
 *
 * One family's slice each, so the AI reads only what it needs:
 *
 * - a quick check, the video lines — their title, stage, the brief's summary and the topics it
 *   `covers`, and the clip that plays. The research AI cannot watch a video, so the brief is all a
 *   question can honestly rest on; a video without one is left out.
 *
 * The other families' slices come with their prompts; until then they have none.
 */
class ActivityPromptContent
{
    public function for(ActivityFamily $family, Version $version): ?string
    {
        $slice = match ($family) {
            ActivityFamily::QUICK_CHECK => $this->videos($version),
            default => null,
        };

        return $slice === null ? null : json_encode($slice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return list<array<string, mixed>> */
    private function videos(Version $version): array
    {
        return $version->stages()->with('cards.items')->get()->values()
            ->flatMap(fn (Stage $stage, int $index) => $stage->cards->flatMap->items
                ->filter(fn (Item $item) => $item->kind === ItemKind::YOUTUBE_VIDEO && $item->is_available && filled($item->brief['summary'] ?? null))
                ->map(fn (Item $item) => array_filter([
                    'key' => $item->key,
                    'stage' => ($index + 1).' · '.$stage->title,
                    'title' => $item->title,
                    'summary' => $item->brief['summary'],
                    'covers' => $item->brief['covers'] ?? null,
                    'clip' => $item->clip_start !== null ? $this->clock($item->clip_start).'–'.($item->clip_end !== null ? $this->clock($item->clip_end) : '…') : null,
                ], fn ($value) => $value !== null)))
            ->values()
            ->all();
    }

    private function clock(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
