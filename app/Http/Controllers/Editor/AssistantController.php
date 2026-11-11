<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\AssistantMessageRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sales assistant's test screen: the owner chats with it as a visitor would, and beside the
 * chat reads what each turn retrieved, decided and cost. The turns are the page's own; the
 * screen says which models answer and whether the provider has a key, so a failing chat can be
 * told from a missing one.
 */
class AssistantController
{
    public function __invoke(): Response
    {
        $provider = config('assistant.provider');

        return Inertia::render('editor/assistant/Index', [
            'provider' => $provider,
            'models' => config('assistant.models'),
            'hasKey' => filled(config("ai.providers.{$provider}.key")),
            'cacheInstructions' => config('assistant.cache_instructions'),
            'maxLength' => AssistantMessageRequest::MAX_LENGTH,
        ]);
    }
}
