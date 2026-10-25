<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\FillPromptRequest;
use App\Http\Resources\FilledPromptResource;
use App\Models\PromptTemplate;
use App\Services\PromptRenderer;

/**
 * The live preview behind every fill-in form: a version of this template, filled with the
 * values typed so far. Any version of the template, used or not — filling one changes nothing.
 */
class PromptFillController
{
    public function __invoke(FillPromptRequest $request, PromptTemplate $template, PromptRenderer $renderer): FilledPromptResource
    {
        $version = $template->versions()->findOrFail($request->validated('version_id'));

        return new FilledPromptResource($renderer->render($version, $request->validated('values')));
    }
}
