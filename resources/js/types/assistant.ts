/**
 * The sales assistant's chat, as the web channel carries it. The server streams one turn as
 * named server-sent events — `turn`, `delta`, `done`, `error` — and these are their payloads,
 * snake_case as Laravel writes them.
 */

export type AssistantRole = 'visitor' | 'assistant';

export type AssistantMessage = {
    id: string;
    role: AssistantRole;
    text: string;
};

/** A passage the retriever handed the assistant for one turn. */
export type AssistantPassage = {
    guide_id: number;
    guide_title: string;
    source: string;
    score: number;
    content: string;
};

/** What one assistant turn cost and decided — read by the editor's metrics tabs. */
export type AssistantTurnMetrics = {
    turn_id: string;
    passages: AssistantPassage[];
    engagement: number;
    usage: {
        input_tokens: number;
        output_tokens: number;
        // Null when the provider does not report cache reads.
        cached_input_tokens: number | null;
    };
    // Null when the model has no price configured.
    cost: number | null;
    conversion: { guide_id: number; guide_title: string } | null;
};

export type AssistantStreamEvent =
    | { type: 'turn'; conversation_id: string; turn_id: string }
    | { type: 'delta'; text: string }
    | { type: 'done'; metrics: AssistantTurnMetrics }
    | { type: 'error'; message: string };

export type AssistantChatStatus =
    | { state: 'idle' }
    | { state: 'replying' }
    | { state: 'failed'; message: string };
