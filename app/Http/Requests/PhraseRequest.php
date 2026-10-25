<?php

namespace App\Http\Requests;

use App\Enums\PhraseMoment;
use App\Models\Phrase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One motivation phrase: the moment it is for, and its text — short, one line. */
class PhraseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['text' => trim((string) $this->input('text'))]);
    }

    public function rules(): array
    {
        return [
            'moment' => ['required', Rule::enum(PhraseMoment::class)],
            'text' => ['required', 'string', 'max:'.Phrase::MAX_LENGTH],
        ];
    }
}
