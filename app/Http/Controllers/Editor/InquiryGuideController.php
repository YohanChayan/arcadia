<?php

namespace App\Http\Controllers\Editor;

use App\Actions\StartGuideFromInquiry;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;

/**
 * "Crear guía" on a request: starts the draft guide that answers it and opens its content, where
 * its research prompt is prepared and copied.
 */
class InquiryGuideController
{
    public function __invoke(int $inquiry, StartGuideFromInquiry $start): RedirectResponse
    {
        $inquiry = Inquiry::query()->where('locale', app()->getLocale())->findOrFail($inquiry);

        $guide = $start->handle($inquiry);

        return to_route(app()->getLocale().'.editor.content.show', ['guide' => $guide->id])
            ->with('success', __('arcadia.editor.prompts.provenance.started'));
    }
}
