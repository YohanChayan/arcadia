<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** What a prompt version is for, or which approach it takes. */
class PromptVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
