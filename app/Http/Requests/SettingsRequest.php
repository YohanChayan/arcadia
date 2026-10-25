<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * This host's settings: the price a guide is offered at, in dollars and cents, and its note; and
 * the length of a step, in whole minutes, the shortest first.
 */
class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Blank or 0: no price — the landing page then states none.
            'price' => ['nullable', 'numeric', 'min:0', 'max:999', 'decimal:0,2'],
            'price_note' => ['nullable', 'string', 'max:24'],
            // Both or neither: a range is saved whole.
            'step_min_minutes' => ['required_with:step_max_minutes', 'integer', 'min:1', 'max:60'],
            'step_max_minutes' => ['required_with:step_min_minutes', 'integer', 'gt:step_min_minutes', 'max:120'],
        ];
    }
}
