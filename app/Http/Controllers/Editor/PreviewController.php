<?php

namespace App\Http\Controllers\Editor;

use App\Enums\Accent;
use App\Enums\Palette;
use App\Models\Guide;
use App\Services\Appearance;
use App\Services\ArcadiaSite;
use App\Services\GuidePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reader's page, for the owner, on any version — a draft included, whatever the guide's
 * status. The same page component and the same presenter as the public link, so what the owner
 * previews is exactly what a reader will get.
 *
 * In one reader's colours with `?reader=`, and in colours not yet saved with `?palette=` and
 * `?accent=` — the editor's live preview, which writes nothing. Otherwise the guide's default.
 */
class PreviewController
{
    public function __invoke(Request $request, Guide $guide, GuidePresenter $presenter, ArcadiaSite $site): Response
    {
        abort_unless($guide->locale === app()->getLocale(), 404);

        $version = $request->filled('version')
            ? $guide->versions()->where('number', $request->integer('version'))->firstOrFail()
            : $guide->latestVersion();

        $reader = $request->filled('reader')
            ? $guide->readers()->findOrFail($request->integer('reader'))
            : null;

        $palette = Palette::tryFrom((string) $request->query('palette')) ?? $reader?->palette ?? $guide->default_palette;
        $accent = Accent::tryFrom((string) $request->query('accent')) ?? $reader?->accent ?? $guide->default_accent;

        return Inertia::render('Guide', [
            ...$presenter->present($guide, $version, withAnswers: true),
            'preview' => true,
            'appearance' => Appearance::tokens($palette, $accent),
            // As that reader is greeted, when the preview is theirs.
            'readerName' => $reader?->displayName(),
            'privacyUrl' => $site->privacyPath(),
            'pageTitle' => $guide->title,
            'noIndex' => true,
        ]);
    }
}
