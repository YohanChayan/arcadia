<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A request for a guide — from the landing page's popup, or written by the owner in the editor:
 * what to learn, and one contact — a WhatsApp number or an email, told apart by the `@`. A name
 * only the editor's form asks for. Kept as forgiving as the data allows: a visitor who types `0414-123.45.67`
 * meant a number.
 *
 * `website` is a field no person sees: a bot that fills every input fills it too.
 */
class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'topic' => trim((string) $this->input('topic')),
            'contact' => trim((string) $this->input('contact')),
        ]);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:200'],
            'contact' => ['required', 'string', 'max:160', $this->reachable(...)],
            'name' => ['nullable', 'string', 'max:120'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic.required' => __('arcadia.landing.ask.errors.topic'),
            'topic.min' => __('arcadia.landing.ask.errors.topic'),
            'contact.required' => __('arcadia.landing.ask.errors.contact'),
        ];
    }

    /** An email, or a phone number: seven to fifteen digits, in any of the ways people write one. */
    private function reachable(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        $isReachable = str_contains($value, '@')
            ? filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            : preg_match('/^[0-9+\s().\-]+$/', $value) === 1
                && preg_match_all('/\d/', $value) >= 7
                && preg_match_all('/\d/', $value) <= 15;

        if (! $isReachable) {
            $fail(__('arcadia.landing.ask.errors.contact'));
        }
    }
}
