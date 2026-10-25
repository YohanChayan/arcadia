<?php

namespace App\Http\Controllers\Reader;

use App\Enums\Accent;
use App\Enums\Palette;
use App\Models\Reader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A reader saves their own colours for one of their guides — from their dashboard or from the
 * guide page. Only their own assignment: anyone else's, or the other language's, is a 404.
 * The editor's badge then reads "Reader".
 */
class AppearanceController
{
    public function __invoke(Request $request, Reader $reader): RedirectResponse
    {
        abort_unless($reader->user_id === $request->user()->id, 404);
        abort_unless($reader->guide->locale === app()->getLocale(), 404);

        $data = $request->validate([
            'palette' => ['required', Rule::enum(Palette::class)],
            'accent' => ['required', Rule::enum(Accent::class)],
        ]);

        $reader->paint(Palette::from($data['palette']), Accent::from($data['accent']), Reader::BY_READER);

        return back()->with('success', __('arcadia.my_guides.flash.saved'));
    }
}
