<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\FillPromptRequest;
use App\Models\Guide;
use App\Models\PromptTemplate;
use App\Services\PromptRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the research prompt the owner just copied from a guide's content screen — the version
 * and the values — and freezes that version, so the guide keeps pointing at exactly the text that
 * researched it. Copying again, in any version of the research template, replaces the record.
 *
 * Refused while a required blank is empty: a prompt with a hole in it is not what was meant.
 */
class GuidePromptController
{
    public function __invoke(FillPromptRequest $request, Guide $guide, PromptRenderer $renderer): RedirectResponse
    {
        abort_unless($guide->locale === app()->getLocale(), 404);

        $version = PromptTemplate::query()->where('name', PromptTemplate::RESEARCH)->firstOrFail()
            ->versions()->findOrFail($request->validated('version_id'));
        // The declared values and the extra instructions; anything else the form sent is not kept.
        $values = array_intersect_key($request->validated('values'), [
            ...$version->variables->keyBy('name')->all(),
            PromptRenderer::EXTRA => true,
        ]);

        $missing = $renderer->render($version, $values)['missing'];

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'values' => __('arcadia.editor.prompts.fill.missing', ['names' => implode(', ', $missing)]),
            ]);
        }

        DB::transaction(function () use ($guide, $version, $values) {
            $version->freeze();

            $guide->forceFill([
                'prompt_version_id' => $version->id,
                'prompt_values' => $values,
            ])->save();
        });

        return back();
    }
}
