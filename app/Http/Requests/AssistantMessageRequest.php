<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One message to the sales assistant from the web chat. A conversation is continued only by the
 * account that started it, on the web; none given starts a new one.
 */
class AssistantMessageRequest extends FormRequest
{
    /** The longest message the chat accepts; the chat's field holds the same limit. */
    public const MAX_LENGTH = 2000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => trim((string) $this->input('message'))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'conversation_id' => [
                'nullable',
                'ulid',
                Rule::exists('assistant_conversations', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('channel', 'web'),
            ],
        ];
    }
}
