<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * This host's settings, in its own language: the price the landing page offers a guide at, the
 * note shown beside it, and how long a guide's step should take to watch.
 */
class SettingsController
{
    public function edit(): Response
    {
        $setting = Setting::forLocale(app()->getLocale());

        return Inertia::render('editor/settings/Edit', [
            'price' => $setting->price,
            'priceNote' => $setting->price_note,
            'stepMin' => $setting->step_min_minutes,
            'stepMax' => $setting->step_max_minutes,
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        Setting::forLocale(app()->getLocale())->fill([
            'price' => $request->validated('price') ?? 0,
            // Blank clears it: the note is shown only when there is one.
            'price_note' => filled($request->validated('price_note')) ? trim($request->validated('price_note')) : null,
            ...$request->safe()->only(['step_min_minutes', 'step_max_minutes']),
        ])->save();

        return back()->with('success', __('arcadia.editor.settings.flash.saved'));
    }
}
