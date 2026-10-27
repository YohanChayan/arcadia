<?php

namespace App\Services;

use App\Enums\ItemKind;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;

/**
 * What a reader is given of one version of a guide — the same for the public link and the
 * editor's preview, so the preview is the page, not an imitation of it.
 *
 * A YouTube item carries the id the player needs, extracted here once: the page never parses a
 * URL, and an item whose link is not a recognisable video or playlist is shown as a link.
 */
class GuidePresenter
{
    /** @return array<string, mixed> */
    public function present(Guide $guide, Version $version): array
    {
        $guide->loadMissing('category');
        $version->loadMissing(['sections', 'stages.cards.items']);

        return [
            'guide' => [
                'id' => $guide->id,
                'title' => $guide->title,
                'level' => $guide->level->label(),
                'category' => $guide->category?->name,
                'goals' => $guide->goals ?? [],
                // The personal opening, shown before anything else. Never the curator's notes.
                'greeting' => $guide->greeting,
                // Its cover, the whole frame, atop the page — or null while none is uploaded.
                'cover' => $guide->cover()?->toPage('hero'),
            ],
            'version' => $version->number,
            'sections' => $version->sections->map->only('key', 'kind', 'title', 'body')->values(),
            'stages' => $version->stages->map(fn ($stage) => [
                ...$stage->only('key', 'title', 'goal'),
                'cards' => $stage->cards->map(fn ($card) => [
                    ...$card->only('key', 'byline', 'description', 'quote', 'closing_note'),
                    'kind' => $card->kind->value,
                    'items' => $card->items->map(fn (Item $item) => $this->item($item))->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(Item $item): array
    {
        $embed = $item->is_available ? $this->embed($item) : null;

        return [
            ...$item->only('key', 'title', 'hint', 'body', 'is_available'),
            // A clipped video opens on YouTube where the clip starts, as the player beside it does.
            'url' => isset($embed['start']) ? "https://www.youtube.com/watch?v={$embed['video']}&t={$embed['start']}s" : $item->url,
            'kind' => $item->kind->value,
            'embed' => $embed,
            'clip' => isset($embed['start']) ? ['start' => $embed['start'], 'end' => $embed['end'] ?? null] : null,
            'minutes' => $this->minutes($item),
        ];
    }

    /**
     * Roughly how long the line takes, for a locked stage's preview: the clip's length when it has
     * an end, else the minutes its brief gives (`6 min`, `8 min de lectura`, `3:33`), else null.
     * Only the number leaves the brief, which is never sent to a reader. Never `hint`, which is
     * free text, and never the step length, which is the research's target, not the line's.
     */
    private function minutes(Item $item): ?int
    {
        if ($item->clip_start !== null && $item->clip_end !== null) {
            return max(1, (int) ceil(($item->clip_end - $item->clip_start) / 60));
        }

        $duration = (string) data_get($item->brief, 'duration');

        if (preg_match('/^\s*(\d+):(\d{2})\b/', $duration, $clock)) {
            return max(1, (int) ceil(((int) $clock[1] * 60 + (int) $clock[2]) / 60));
        }

        if (preg_match('/^\s*(\d+)\s*min/i', $duration, $count)) {
            return max(1, (int) $count[1]);
        }

        return null;
    }

    /**
     * The player's target for a YouTube item — `['video' => id]` or `['playlist' => id]` — or
     * null when there is nothing to embed. A clipped video adds its `start` and `end`, YouTube's
     * own embed parameters, in seconds from the start of the video; the player is not modified.
     *
     * @return array{video?: string, playlist?: string, start?: int, end?: int}|null
     */
    public function embed(Item $item): ?array
    {
        if (! in_array($item->kind, [ItemKind::YOUTUBE_VIDEO, ItemKind::YOUTUBE_PLAYLIST], true) || ! $item->url) {
            return null;
        }

        $parts = parse_url($item->url);
        $host = preg_replace('/^(www\.|m\.)/', '', strtolower($parts['host'] ?? ''));
        parse_str($parts['query'] ?? '', $query);
        $path = trim($parts['path'] ?? '', '/');

        if ($item->kind === ItemKind::YOUTUBE_PLAYLIST) {
            $list = $query['list'] ?? null;

            return $host === 'youtube.com' && is_string($list) && preg_match('/^[\w-]+$/', $list) ? ['playlist' => $list] : null;
        }

        $id = match (true) {
            $host === 'youtu.be' => explode('/', $path)[0],
            $host === 'youtube.com' && $path === 'watch' => $query['v'] ?? null,
            $host === 'youtube.com' && preg_match('#^(shorts|embed|live)/([\w-]+)#', $path, $m) === 1 => $m[2],
            default => null,
        };

        if (! is_string($id) || preg_match('/^[\w-]{6,20}$/', $id) !== 1) {
            return null;
        }

        return array_filter([
            'video' => $id,
            'start' => $item->clip_start,
            'end' => $item->clip_end,
        ], fn ($value) => $value !== null);
    }
}
