<?php

namespace App\Services;

use App\Enums\ActivityFamily;
use App\Enums\ItemKind;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;

/**
 * What a reader is given of one version of a guide — the same for the public link and the
 * editor's preview, so the preview is the page, not an imitation of it.
 *
 * Each node's `activity` passes through `ActivityFamily::forPage()`: never its evidence, and a
 * quick check's answers only `$withAnswers` — for the owner and the preview, never a reader.
 *
 * A YouTube item carries the id the player needs, extracted here once: the page never parses a
 * URL, and an item whose link is not a recognisable video or playlist is shown as a link.
 */
class GuidePresenter
{
    /** @return array<string, mixed> */
    public function present(Guide $guide, Version $version, bool $withAnswers = false): array
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
            'sections' => $version->sections->map(fn ($section) => [
                ...$section->only('key', 'kind', 'title', 'body'),
                'activity' => ActivityFamily::forPage($section->activity, $section, $withAnswers),
            ])->values(),
            'stages' => $version->stages->map(fn ($stage) => [
                ...$stage->only('key', 'title', 'goal'),
                'activity' => ActivityFamily::forPage($stage->activity, $stage, $withAnswers),
                'cards' => $stage->cards->map(fn ($card) => [
                    ...$card->only('key', 'byline', 'description', 'quote', 'closing_note'),
                    'kind' => $card->kind->value,
                    'items' => $card->items->map(fn (Item $item) => $this->item($item, $withAnswers))->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(Item $item, bool $withAnswers): array
    {
        $embed = $item->is_available ? $this->embed($item) : null;

        return [
            ...$item->only('key', 'title', 'hint', 'body', 'is_available'),
            // A clipped video opens on YouTube where the clip starts, as the player beside it does.
            'url' => isset($embed['start']) ? "https://www.youtube.com/watch?v={$embed['video']}&t={$embed['start']}s" : $item->url,
            'kind' => $item->kind->value,
            'embed' => $embed,
            'clip' => isset($embed['start']) ? ['start' => $embed['start'], 'end' => $embed['end'] ?? null] : null,
            'minutes' => $item->minutes(),
            'activity' => ActivityFamily::forPage($item->activity, $item, $withAnswers),
        ];
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
