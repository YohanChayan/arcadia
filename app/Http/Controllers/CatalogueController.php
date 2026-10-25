<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Setting;
use App\Services\ArcadiaSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalogue (ADR 0023): this language's guides that anyone may ask for, newest first, each
 * card opening the guide's presentation — what it is about, never what is in it. A card carries
 * no more than the presentation shows. Grouped by category, chosen on the page. A guide not
 * there is asked for from the same popup as on the landing page.
 *
 * Not indexed yet, as the presentations it links to are not (ADR 0022).
 */
class CatalogueController
{
    public function __invoke(Request $request, ArcadiaSite $site): Response
    {
        $guides = Guide::query()
            ->listed()
            ->where('locale', $site->locale())
            ->with(['category', 'images'])
            ->latest()
            ->get();

        $setting = Setting::forLocale($site->locale());

        // The catalogue in Arcadia's other languages.
        $alternates = [];
        foreach ($site->locales() as $locale) {
            if ($locale !== $site->locale()) {
                $alternates[$locale] = $site->url($site->cataloguePath($locale));
            }
        }

        return Inertia::render('Catalogue', [
            'guides' => $guides->map(fn (Guide $guide) => [
                'url' => '/'.$site->guidePath().'/'.$guide->link_token,
                'title' => $guide->title,
                'level' => $guide->level->label(),
                'category' => $guide->category_id,
                'goals' => $guide->goals ?? [],
                'pitch' => $guide->pitch,
                'cover' => $guide->cover()?->toPage('share'),
            ]),
            // Only the categories something is listed in, so no chip filters down to nothing.
            'categories' => $guides->pluck('category')->filter()->unique('id')->sortBy('name')
                ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
            'pageTitle' => __('arcadia.catalogue.meta.title'),
            'metaDescription' => __('arcadia.catalogue.meta.description'),
            ...$site->preview(),
            'noIndex' => true,
            'canonicalUrl' => $site->url($site->cataloguePath()),
            'alternates' => $alternates,
            'homeUrl' => $site->homePath(),
            'inquiryUrl' => $site->inquiryPath(),
            'requester' => $request->user()?->hasVerifiedEmail() ? $request->user()->only('name', 'email') : null,
            'contactEmail' => $site->contactEmail(),
            // What the request popup says a guide costs, as the landing page says it.
            'price' => $setting->hasPrice() ? $setting->priceLabel() : null,
            'priceNote' => $setting->hasPrice() ? $setting->price_note : null,
        ]);
    }
}
