<?php

namespace App\Http\Controllers\Editor;

use App\Enums\PhraseMoment;
use App\Http\Requests\PhraseRequest;
use App\Models\Phrase;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * This language's motivation phrases, grouped by the moment they are shown at, each with how many
 * guides chose it. Added, changed and deleted here; a guide chooses its own in its form.
 *
 * Another language's phrase is a 404 here, whatever id is in the URL, as every editor screen is.
 */
class PhraseController
{
    public function index(): Response
    {
        return Inertia::render('editor/phrases/Index', [
            'phrases' => Phrase::query()->where('locale', app()->getLocale())->withCount('guides')->oldest('id')->get()
                ->map(fn (Phrase $phrase) => [
                    'id' => $phrase->id,
                    'moment' => $phrase->moment->value,
                    'text' => $phrase->text,
                    'guides_count' => $phrase->guides_count,
                ]),
            'moments' => PhraseMoment::options(),
            'maxLength' => Phrase::MAX_LENGTH,
        ]);
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
