<?php

namespace App\Http\Requests;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Enums\PhraseMoment;
use App\Models\Guide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guide's description, for store and update alike.
 *
 * Category and topics must be this editor's language's own: the locale is the URL's
 * (`/es/editor` sets Spanish), so a Spanish guide can only be filed under Spanish terms. Goals are
 * the guide's own lines of text, written here (ADR 0020).
 */
class GuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locale = app()->getLocale();

        return [
            'title' => ['required', 'string', 'max:160'],
            'level' => ['required', Rule::enum(Level::class)],
            'status' => ['required', Rule::enum(GuideStatus::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('guide_categories', 'id')->where('locale', $locale)],
            'topic_ids' => ['array'],
            'topic_ids.*' => ['integer', Rule::exists('guide_topics', 'id')->where('locale', $locale)],
            'goals' => ['nullable', 'array', 'max:'.Guide::MAX_GOALS],
            'goals.*' => ['nullable', 'string', 'max:160'],
            // The motivation phrases it draws on; none, and it draws on all of its language's. Only
            // for the moments a guide chooses: the welcome is every guide's.
            'phrase_ids' => ['array'],
            'phrase_ids.*' => ['integer', Rule::exists('guide_phrases', 'id')->where('locale', $locale)
                ->whereIn('moment', array_map(fn (PhraseMoment $moment) => $moment->value, PhraseMoment::perGuide()))],
            'greeting' => ['nullable', 'string', 'max:2000'],
            // Whether a reader opens its stages one after another.
            'unlock_in_order' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('arcadia.editor.guides.errors.title'),
            'category_id.exists' => __('arcadia.editor.guides.errors.vocabulary'),
            'topic_ids.*.exists' => __('arcadia.editor.guides.errors.vocabulary'),
            'phrase_ids.*.exists' => __('arcadia.editor.guides.errors.vocabulary'),
            'goals.max' => __('arcadia.editor.guides.errors.goals', ['max' => Guide::MAX_GOALS]),
        ];
    }
}
