<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A version of a prompt, and the values to fill it with, by variable name. */
class FillPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version_id' => ['required', 'integer'],
            'values' => ['present', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
