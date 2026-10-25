<?php

namespace App\Http\Controllers\Editor;

use App\Enums\Accent;
use App\Enums\Palette;
use App\Models\Guide;
use App\Models\Reader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A guide's colours, set by the owner: the default each new reader starts from, and one
 * reader's own.
 *
 * The default is copied when a reader is assigned and never after, so saving it repaints
 * nobody already assigned. Saving over colours the reader chose themselves needs `overwrite` —
 * the screen asks first — so the owner never undoes a reader's choice by accident.
 */
class AppearanceController
{
    public function guide(Request $request, Guide $guide): RedirectResponse
    {
        $this->ownLocale($guide);
        [$palette, $accent] = $this->validated($request);

        $guide->forceFill(['default_palette' => $palette, 'default_accent' => $accent])->save();

        return back()->with('success', __('arcadia.editor.appearance.flash.default'));
    }

    public function reader(Request $request, Guide $guide, Reader $reader): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($reader->guide_id === $guide->id, 404);
        [$palette, $accent] = $this->validated($request);

        if ($reader->appearance_by === Reader::BY_READER && ! $request->boolean('overwrite')) {
            throw ValidationException::withMessages(['overwrite' => __('arcadia.editor.appearance.errors.overwrite')]);
        }

        $reader->paint($palette, $accent, Reader::BY_ADMIN);

        return back()->with('success', __('arcadia.editor.appearance.flash.reader', ['email' => $reader->email]));
    }

    /** @return array{Palette, Accent} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'palette' => ['required', Rule::enum(Palette::class)],
            'accent' => ['required', Rule::enum(Accent::class)],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        return [Palette::from($data['palette']), Accent::from($data['accent'])];
    }

    /** A guide of the other language is not this editor's, whatever the id in the URL. */
    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }
}
