<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\PhraseRequest;
use App\Models\Phrase;
use Illuminate\Http\RedirectResponse;

/**
 * This language's motivation phrases, added, changed and deleted here. They are listed in the
 * vocabulary screen's phrases tab; a guide chooses its own in its form.
 *
 * Another language's phrase is a 404 here, whatever id is in the URL, as every editor screen is.
 */
class PhraseController
{
    /** The phrases are a tab of the vocabulary screen; this address opens it. */
    public function index(): RedirectResponse
    {
        return to_route(app()->getLocale().'.editor.vocabulary.index', ['tab' => 'phrases']);
    }

    public function store(PhraseRequest $request): RedirectResponse
    {
        Phrase::create(['locale' => app()->getLocale(), ...$request->validated()]);

        return back()->with('success', __('arcadia.editor.phrases.flash.saved'));
    }

    public function update(PhraseRequest $request, int $phrase): RedirectResponse
    {
        $this->find($phrase)->update($request->validated());

        return back()->with('success', __('arcadia.editor.phrases.flash.saved'));
    }

    /** Taken from every guide that chose it; the guides stay. */
    public function destroy(int $phrase): RedirectResponse
    {
        $this->find($phrase)->delete();

        return back()->with('success', __('arcadia.editor.phrases.flash.deleted'));
    }

    private function find(int $id): Phrase
    {
        return Phrase::query()->where('locale', app()->getLocale())->findOrFail($id);
    }
}
