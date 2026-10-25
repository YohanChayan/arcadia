<?php

namespace App\Http\Controllers\Editor;

use App\Models\AccessRequest;
use App\Models\Guide;
use App\Services\ArcadiaSite;
use App\Services\GuidePresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a guide's link shows someone who may not read it (ADR 0022): its pitch, written here, and
 * the page itself, previewed as a visitor who may ask to enroll — whether or not it is open yet.
 */
class PresentationController
{
    /** The pitch: a few lines to present the guide. Blank clears it. */
    public function update(Request $request, Guide $guide): RedirectResponse
    {
        $this->ownLocale($guide);

        $pitch = $request->validate(['pitch' => ['nullable', 'string', 'max:'.Guide::MAX_PITCH]])['pitch'] ?? null;
        $guide->forceFill(['pitch' => filled($pitch) ? trim($pitch) : null])->save();

        return back()->with('success', __('arcadia.editor.images.flash.pitch_saved'));
    }

    public function preview(Guide $guide, ArcadiaSite $site, GuidePresentation $presentation): Response
    {
        $this->ownLocale($guide);

        return Inertia::render('GuidePresentation', [
            ...$presentation->present($guide, $site),
            'state' => 'open',
            'signInUrl' => null,
            'email' => null,
            'myGuidesUrl' => null,
            'accessUrl' => null,
            'messageMax' => AccessRequest::MAX_MESSAGE,
            'preview' => true,
        ]);
    }

    /** A guide of the other language is not this editor's, whatever the id in the URL. */
    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }
}
