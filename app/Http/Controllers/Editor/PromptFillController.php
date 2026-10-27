<?php

namespace App\Http\Controllers\Editor;

use App\Enums\ActivityFamily;
use App\Http\Requests\FillPromptRequest;
use App\Http\Resources\FilledPromptResource;
use App\Models\Guide;
use App\Models\PromptTemplate;
use App\Services\ActivityPromptContent;
use App\Services\PromptRenderer;

/**
 * The live preview behind every fill-in form: a version of this template, filled with the
 * values typed so far. Any version of the template, used or not — filling one changes nothing.
 */
class PromptFillController
{
    public function __invoke(FillPromptRequest $request, PromptTemplate $template, PromptRenderer $renderer, ActivityPromptContent $content): FilledPromptResource
    {
        $version = $template->versions()->findOrFail($request->validated('version_id'));

        return new FilledPromptResource($renderer->render($version, $request->validated('values'), $this->content($request, $template, $content)));
    }

    /** A guide's content, for an activity prompt opened on one of this editor's guides; else none. */
    private function content(FillPromptRequest $request, PromptTemplate $template, ActivityPromptContent $content): ?string
    {
        $family = ActivityFamily::forTemplate($template->name);

        if (! $family || ! $request->filled('guide_id')) {
            return null;
        }

        $guide = Guide::query()->where('locale', app()->getLocale())->findOrFail($request->validated('guide_id'));

        return $content->for($family, $guide->latestVersion());
    }
}
