/**
 * The sales assistant's chat, as the web channel carries it. The server streams one turn as
 * named server-sent events — `turn`, `delta`, `done`, `error` — and these are their payloads,
 * snake_case as Laravel writes them.
 */
import type { AssistantTurnMetrics } from './metrics';

export type AssistantRole = 'visitor' | 'assistant';

export type AssistantMessage = {
    id: string;
    role: AssistantRole;
    text: string;
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
