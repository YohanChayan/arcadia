<?php

namespace App\Http\Controllers\Editor;

use App\Assistant\Channels\WebChannel;
use App\Assistant\Models\Conversation;
use App\Assistant\Orchestrator;
use App\Http\Requests\AssistantMessageRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One visitor turn from the editor's test chat, answered as a stream of server-sent events. The
 * owner is the visitor here; the conversation is theirs, in this editor's language when the
 * assistant speaks it, else in its first.
 */
class AssistantMessageController
{
    public function __invoke(AssistantMessageRequest $request, Orchestrator $orchestrator): StreamedResponse
    {
        $channel = new WebChannel;
        $conversation = $request->filled('conversation_id')
            ? Conversation::query()->whereKey($request->validated('conversation_id'))->firstOrFail()
            : $orchestrator->start($channel->name(), $this->locale(), $request->user());

        $message = (string) $request->validated('message');

        return response()->stream(function () use ($orchestrator, $conversation, $message, $channel): void {
            // A reader who closes the tab mid-reply has still been charged for it: the turn
            // finishes and is recorded rather than dying at the next write.
            ignore_user_abort(true);

            $orchestrator->reply($conversation, $message, $channel);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            // `no-transform` keeps Caddy's `encode` from compressing — and so holding back — the
            // stream; `X-Accel-Buffering` turns nginx's FastCGI buffering off for this response.
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function locale(): string
    {
        $locales = config('assistant.locales');

        return in_array(app()->getLocale(), $locales, true) ? app()->getLocale() : $locales[0];
    }
}
