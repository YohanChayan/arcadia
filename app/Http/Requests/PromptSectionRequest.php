<?php

namespace App\Http\Requests;

use App\Enums\PromptSectionKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One section of a prompt version: its kind, an optional heading, and its text. */
class PromptSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(PromptSectionKind::class)],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }
}
