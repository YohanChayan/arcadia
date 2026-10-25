<?php

namespace App\Services;

use App\Models\Guide;

/**
 * What the guide's link shows someone who may not read it (ADR 0022): its cover, title, level,
 * category, goals and pitch — what it is about, never what is in it. No section, stage or item,
 * no greeting, no readers, no price. Its preview is the guide's own share card, and it is never
 * indexed.
 */
class GuidePresentation
{
    /** @return array<string, mixed> */
    public function present(Guide $guide, ArcadiaSite $site): array
    {
        $guide->loadMissing('category');
        $cover = $guide->cover();

        return [
            'guide' => [
                'title' => $guide->title,
                'level' => $guide->level->label(),
                'category' => $guide->category?->name,
                'goals' => $guide->goals ?? [],
                'pitch' => $guide->pitch,
                'cover' => $cover?->toPage('hero'),
            ],
            'homeUrl' => $site->homePath(),
            'pageTitle' => $guide->title.' · Arcadia',
            'metaDescription' => $guide->pitch
                ? (string) str($guide->pitch)->squish()->limit(160)
                : __('arcadia.landing.meta.description'),
            // Shared by link, not yet listed: nothing here for a search engine.
            'noIndex' => true,
            'canonicalUrl' => $site->guideUrl($guide->link_token),
            ...$site->preview(),
            // A shared link previews as this guide once it has a cover; Arcadia's own image until then.
            ...($cover ? ['ogImage' => $cover->url('share'), 'ogImageAlt' => $guide->title] : []),
        ];
    }
}
