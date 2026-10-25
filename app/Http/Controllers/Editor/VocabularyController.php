<?php

namespace App\Http\Controllers\Editor;

use App\Enums\Vocabulary;
use App\Http\Requests\VocabularyTermRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's vocabularies for this site's language — categories and topics — on one screen.
 * One controller because they differ only in what `Vocabulary` answers.
 */
class VocabularyController
{
    public function index(): Response
    {
        $lists = [];

        foreach (Vocabulary::cases() as $vocabulary) {
            $lists[$vocabulary->value] = $vocabulary->model()::query()
                ->where('locale', app()->getLocale())
                ->withCount('guides')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('editor/vocabulary/Index', ['lists' => $lists]);
    }

    public function store(VocabularyTermRequest $request, string $vocabulary): RedirectResponse
    {
        Vocabulary::from($vocabulary)->model()::create([
            'locale' => app()->getLocale(),
            ...$request->safe()->only(['name']),
        ]);

        return back()->with('success', __('arcadia.editor.vocabulary.flash.saved'));
    }

    public function update(VocabularyTermRequest $request, string $vocabulary, int $term): RedirectResponse
    {
        $this->find($vocabulary, $term)->update($request->safe()->only(['name']));

        return back()->with('success', __('arcadia.editor.vocabulary.flash.saved'));
    }

    /**
     * A category in use leaves its guides uncategorised; a topic is unfiled from them.
     * The screen says how many guides use a term before it is deleted.
     */
    public function destroy(string $vocabulary, int $term): RedirectResponse
    {
        $this->find($vocabulary, $term)->delete();

        return back()->with('success', __('arcadia.editor.vocabulary.flash.deleted'));
    }

    private function find(string $vocabulary, int $term)
    {
        return Vocabulary::from($vocabulary)->model()::query()
            ->where('locale', app()->getLocale())
            ->findOrFail($term);
    }
}
