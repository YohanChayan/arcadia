<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reader added to a guide: an email, once per guide, compared lower-cased as it is stored; and
 * whether to tell them by email (on unless unticked).
 */
class ReaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                // A removed reader may be added back: `AssignReader` restores them.
                Rule::unique('guide_readers', 'email')->where('guide_id', $this->route('guide')->id)->whereNull('deleted_at'),
            ],
            'notify' => ['sometimes', 'boolean'],
            // What the guide calls them. Optional.
            'name' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('arcadia.editor.readers.errors.email'),
            'email.email' => __('arcadia.editor.readers.errors.email'),
            'email.unique' => __('arcadia.editor.readers.errors.taken'),
        ];
    }
}
