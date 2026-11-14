<?php

namespace App\Http\Requests;

use App\Models\Guide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guides of this locale to file under one category, from the categorizer. A guide already in
 * another category moves only when `move` says so: the screen asks first, and this refuses a
 * request that did not, so a stale screen cannot silently re-file a guide.
 */
class CategoryGuidesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guide_ids' => ['required', 'array', 'min:1'],
            'guide_ids.*' => ['integer', 'distinct', Rule::exists('guides', 'id')->where('locale', app()->getLocale())],
            'move' => ['boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->boolean('move')) {
                    return;
                }

                $filedElsewhere = Guide::query()
                    ->whereKey($this->input('guide_ids'))
                    ->whereNotNull('category_id')
                    ->where('category_id', '!=', (int) $this->route('term'))
                    ->count();

                if ($filedElsewhere > 0) {
                    $validator->errors()->add('guide_ids', trans_choice('arcadia.editor.vocabulary.categorizer.errors.filed_elsewhere', $filedElsewhere, ['count' => $filedElsewhere]));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guide_ids.required' => __('arcadia.editor.vocabulary.categorizer.errors.none'),
            'guide_ids.*.exists' => __('arcadia.editor.guides.errors.vocabulary'),
        ];
    }
}
