<?php

namespace App\Http\Requests;

use App\Assistant\Channels\WebChannel;
use App\Assistant\Models\Conversation;
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
                    ->where('channel', WebChannel::NAME),
            ],
        ];
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }

    /**
     * The conversation this message goes on with, or null when it starts a new one. Scoped again
     * to its owner and channel, so the lookup never leans on the rule alone.
     */
    public function conversation(): ?Conversation
    {
        $id = $this->validated('conversation_id');

        if ($id === null) {
            return null;
        }

        return Conversation::query()
            ->continuableBy($this->user(), WebChannel::NAME)
            ->whereKey($id)
            ->firstOrFail();
    }

    /** A new conversation's language: this editor's, when the assistant speaks it, else its first. */
    public function assistantLocale(): string
    {
        $locales = config('assistant.locales');

        return in_array(app()->getLocale(), $locales, true) ? app()->getLocale() : $locales[0];
    }
}
