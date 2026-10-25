<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ArcadiaSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Arcadia's home: what a guide is, how one is made, and how to ask for one — a popup that
 * posts to `inquiryUrl` and lands in the editor. Anyone may ask; a signed-in visitor with a
 * verified email is the `requester`, whose account the popup shows it will record. Not indexed for now: Arcadia has no SEO
 * yet (decided 2026-10-13).
 */
class LandingController
{
    public function __invoke(Request $request, ArcadiaSite $site): Response
    {
        // The landing page in Arcadia's other languages.
        $alternates = [];
        foreach ($site->locales() as $locale) {
            if ($locale !== $site->locale()) {
                $alternates[$locale] = $site->url($site->homePath($locale));
            }
        }

        return Inertia::render('Landing', [
            'pageTitle' => __('arcadia.landing.meta.title'),
            'metaDescription' => __('arcadia.landing.meta.description'),
            ...$site->preview(),
            'noIndex' => true,
            'canonicalUrl' => $site->url($site->homePath()),
            'alternates' => $alternates,
            'inquiryUrl' => $site->inquiryPath(),
            'catalogueUrl' => $site->cataloguePath(),
            'requester' => $request->user()?->hasVerifiedEmail() ? $request->user()->only('name', 'email') : null,
            // None at all when the owner set no price: the page states only what a guide is.
            ...$this->price(Setting::forLocale($site->locale())),
            'privacyUrl' => $site->privacyPath(),
            'contactEmail' => $site->contactEmail(),
            'loginUrl' => '/login',
        ]);
    }

    /** The price as the page states it, and its note — both null when there is no price. */
    private function price(Setting $setting): array
    {
        return [
            'price' => $setting->hasPrice() ? $setting->priceLabel() : null,
            'priceNote' => $setting->hasPrice() ? $setting->price_note : null,
        ];
    }
}
