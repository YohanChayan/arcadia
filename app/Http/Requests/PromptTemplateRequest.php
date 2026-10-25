<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A prompt template's name and what it is for. */
class PromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
