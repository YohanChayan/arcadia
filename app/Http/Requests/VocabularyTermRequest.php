<?php

namespace App\Http\Requests;

use App\Enums\Vocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * One term of a vocabulary, for store and update alike: a name, unique within this locale's
 * list — checked on the slug the name becomes, so "Excel" and "excel" are one term.
 */
class VocabularyTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $vocabulary = Vocabulary::from($this->route('vocabulary'));
        $ignore = $this->route('term');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                Rule::unique($vocabulary->table(), 'slug')
                    ->where('locale', app()->getLocale())
                    ->ignore($ignore),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('arcadia.editor.vocabulary.errors.name'),
            'slug.unique' => __('arcadia.editor.vocabulary.errors.taken'),
        ];
    }
}
