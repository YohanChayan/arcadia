<?php

namespace App\Http\Controllers\Editor;

use App\Actions\DraftNextPromptVersion;
use App\Http\Requests\PromptVersionRequest;
use App\Models\PromptTemplate;
use Illuminate\Http\RedirectResponse;

/**
 * A template's versions: a new one, copied from its latest and opened for editing; and any
 * version's description, frozen or not — it labels the version and is no part of the prompt.
 */
class PromptVersionController
{
    public function store(PromptTemplate $template, DraftNextPromptVersion $draft): RedirectResponse
    {
        $version = $draft->handle($template);

        return to_route(app()->getLocale().'.editor.prompts.show', [$template, 'version' => $version->number])
            ->with('success', __('arcadia.editor.prompts.flash.versioned', ['number' => $version->number]));
    }

    public function update(PromptVersionRequest $request, PromptTemplate $template, int $version): RedirectResponse
    {
        $template->versions()->findOrFail($version)->update($request->validated());

        return back()->with('success', __('arcadia.editor.prompts.flash.saved'));
    }
}
