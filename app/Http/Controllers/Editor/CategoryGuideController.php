<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\CategoryGuidesRequest;
use App\Models\Category;
use App\Models\Guide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * The categorizer on the Vocabulary screen: which of this locale's guides a category holds, filed
 * several at once or unfiled one by one.
 *
 * Each guide is saved on its own rather than with one bulk update, so `Guide::updated` fires and
 * the knowledge index re-reads the category it now promises (`KnowledgeServiceProvider`).
 */
class CategoryGuideController
{
    public function store(CategoryGuidesRequest $request, int $term): RedirectResponse
    {
        $category = $this->category($term);

        DB::transaction(function () use ($request, $category) {
            Guide::query()
                ->where('locale', $category->locale)
                ->whereKey($request->validated('guide_ids'))
                ->get()
                ->each(fn (Guide $guide) => $guide->update(['category_id' => $category->id]));
        });

        return back()->with('success', __('arcadia.editor.vocabulary.categorizer.flash.filed'));
    }

    public function destroy(int $term, int $guide): RedirectResponse
    {
        $category = $this->category($term);

        $category->guides()->where('locale', $category->locale)->findOrFail($guide)->update(['category_id' => null]);

        return back()->with('success', __('arcadia.editor.vocabulary.categorizer.flash.unfiled'));
    }

    private function category(int $term): Category
    {
        return Category::query()->where('locale', app()->getLocale())->findOrFail($term);
    }
}
