<?php

namespace App\Http\Controllers\Editor;

use App\Actions\StoreGuideImage;
use App\Enums\ImageSlot;
use App\Http\Requests\GuideImageRequest;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Models\PromptTemplate;
use App\Services\PromptFormOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A guide's images, by slot — one cover, several flyers (`ImageSlot::maxImages()`) — each uploaded
 * as the owner generated it, with the crops each surface shows cut from it. Adding or replacing one
 * needs no new version — an image is the guide's, not a version's.
 * The screen also carries the guide's pitch, and the prompts its cover and flyer are generated from
 * (`PromptTemplate::COVER`, `PromptTemplate::GUIDE_FLYER`). What its presentation brought in is on its card in the list.
 */
class ImageController
{
    public function index(Guide $guide, PromptFormOptions $options): Response
    {
        $this->ownLocale($guide);
        $images = $guide->images()->oldest('id')->get()->groupBy(fn (GuideImage $image) => $image->slot->value);

        return Inertia::render('editor/guides/Images', [
            'guide' => $guide->only('id', 'title', 'pitch'),
            'pitchMax' => Guide::MAX_PITCH,
            // The cover prompt in every version, latest last, for the owner to pick one; and what the
            // guide already says for its blanks — only what a cover needs; the scene is theirs to add.
            'coverPrompt' => $options->template(PromptTemplate::COVER),
            'coverPromptValues' => [
                'title' => $guide->title,
                'level' => $guide->level->value,
                'category' => $guide->category?->name ?? '',
                'pitch' => $guide->pitch ?? '',
                'goals' => implode("\n", $guide->goals ?? []),
            ],
            // The flyer prompt likewise: the guide's own words for its text; the price, the contact
            // and the format come from its variables' defaults.
            'flyerPrompt' => $options->template(PromptTemplate::GUIDE_FLYER),
            'flyerPromptValues' => [
                'title' => $guide->title,
                'category' => $guide->category?->name ?? '',
                'pitch' => $guide->pitch ?? '',
                'goals' => implode("\n", $guide->goals ?? []),
                'language' => $guide->locale,
            ],
            'choices' => $options->choices(app()->getLocale()),
            // Each slot's images in the order they were added.
            'slots' => collect(ImageSlot::cases())->map(fn (ImageSlot $slot) => [
                ...$slot->rules(),
                'images' => $images->get($slot->value, collect())->map->toEditor()->values(),
            ]),
        ]);
    }

    public function store(GuideImageRequest $request, Guide $guide, ImageSlot $slot, StoreGuideImage $store): RedirectResponse
    {
        $this->ownLocale($guide);

        $store->handle($guide, $slot, $request->file('image'));

        return back()->with('success', __('arcadia.editor.images.flash.saved', ['slot' => $slot->label()]));
    }

    /** One image, by its id — a slot of many has no other way to name it. Bound within its guide. */
    public function destroy(Guide $guide, GuideImage $image): RedirectResponse
    {
        $this->ownLocale($guide);

        // Its files go with the row.
        $image->delete();

        return back()->with('success', __('arcadia.editor.images.flash.removed', ['slot' => $image->slot->label()]));
    }

    /** A guide of the other language is not this editor's, whatever the id in the URL. */
    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }
}
