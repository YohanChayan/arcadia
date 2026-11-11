<?php

namespace App\Assistant\Agents;

use App\Assistant\Models\Turn;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

/**
 * The conversation so far, as the model is shown it: each completed turn's message and reply,
 * bare — without the per-turn context that travelled with the message — so the prefix of
 * instructions and history stays the same from one turn to the next.
 */
trait ShowsHistory
{
    public function messages(): iterable
    {
        return $this->history->flatMap(fn (Turn $turn): array => [
            new UserMessage($turn->visitor_message),
            new AssistantMessage((string) $turn->reply),
        ])->all();
    }
}
