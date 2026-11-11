/**
 * One conversation with the sales assistant over the web channel: the messages on screen, the
 * conversation the server keeps them under, and the one request per visitor turn.
 *
 * A turn is a POST answered with a stream (see `readEventStream`): `turn` names the conversation
 * and the turn, `delta` grows the reply in place, `done` carries the turn's metrics, `error`
 * ends it. The page decides where the chat posts and what context goes with every message, so
 * the same chat runs in the editor's test screen and, later, on the landing page.
 */
import { trans } from 'laravel-vue-i18n';
import { onScopeDispose, ref, toValue } from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { readEventStream } from '@/lib/event-stream';
import type { ServerSentEvent } from '@/lib/event-stream';
import type {
    AssistantChatStatus,
    AssistantMessage,
    AssistantStreamEvent,
    AssistantTurnMetrics,
} from '@/types';

export type AssistantChatOptions = {
    url: MaybeRefOrGetter<string>;
    context?: MaybeRefOrGetter<Record<string, string>>;
    onConversationStarted?: (conversationId: string) => void;
    onTurnCompleted?: (metrics: AssistantTurnMetrics) => void;
};

export type AssistantChat = {
    messages: Ref<AssistantMessage[]>;
    status: Ref<AssistantChatStatus>;
    conversationId: Ref<string | null>;
    send: (text: string) => Promise<void>;
    retry: () => Promise<void>;
    reset: () => void;
};

export function useAssistantChat(options: AssistantChatOptions): AssistantChat {
    const messages = ref<AssistantMessage[]>([]);
    const status = ref<AssistantChatStatus>({ state: 'idle' });
    const conversationId = ref<string | null>(null);

    let inFlight: AbortController | null = null;
    let lastVisitorText = '';

    async function send(text: string): Promise<void> {
        const message = text.trim();

        if (message === '' || status.value.state === 'replying') {
            return;
        }

        lastVisitorText = message;
        messages.value.push({ id: localId(), role: 'visitor', text: message });

        await requestReply(message);
    }

    // The visitor's message stays on screen; only the reply is asked for again.
    async function retry(): Promise<void> {
        if (status.value.state !== 'failed' || lastVisitorText === '') {
            return;
        }

        await requestReply(lastVisitorText);
    }

    function reset(): void {
        inFlight?.abort();
        messages.value = [];
        conversationId.value = null;
        status.value = { state: 'idle' };
        lastVisitorText = '';
    }

    async function requestReply(message: string): Promise<void> {
        status.value = { state: 'replying' };
        inFlight = new AbortController();

        const reply: AssistantMessage = {
            id: localId(),
            role: 'assistant',
            text: '',
        };
        messages.value.push(reply);
        // The reactive copy, so each delta re-renders the bubble.
        const replyOnScreen = messages.value[messages.value.length - 1];

        try {
            const response = await fetch(toValue(options.url), {
                method: 'POST',
                headers: {
                    Accept: 'text/event-stream',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    message,
                    conversation_id: conversationId.value,
                    context: toValue(options.context) ?? {},
                }),
                signal: inFlight.signal,
            });

            if (!response.ok) {
                throw new Error(await failureMessage(response));
            }

            let failure: string | null = null;

            await readEventStream(response, (event) => {
                const parsed = parseStreamEvent(event);

                if (parsed === null) {
                    return;
                }

                if (parsed.type === 'turn') {
                    if (conversationId.value !== parsed.conversation_id) {
                        conversationId.value = parsed.conversation_id;
                        options.onConversationStarted?.(parsed.conversation_id);
                    }
                } else if (parsed.type === 'delta') {
                    replyOnScreen.text += parsed.text;
                } else if (parsed.type === 'done') {
                    options.onTurnCompleted?.(parsed.metrics);
                } else {
                    failure = parsed.message;
                }
            });

            if (failure !== null) {
                throw new Error(failure);
            }

            status.value = { state: 'idle' };
        } catch (error) {
            // A reset or an unmount aborted it on purpose; there is nothing to report.
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            messages.value = messages.value.filter(
                (each) => each.id !== reply.id,
            );
            status.value = {
                state: 'failed',
                message:
                    error instanceof Error && error.message !== ''
                        ? error.message
                        : trans('arcadia.assistant.failed'),
            };
        } finally {
            inFlight = null;
        }
    }

    onScopeDispose(() => inFlight?.abort());

    return { messages, status, conversationId, send, retry, reset };
}

/**
 * The one place that knows the wire format. The server is ours and writes these from the same
 * contract, so a `done` payload is trusted once it is an object; an event this client does not
 * know is ignored rather than failed on, so the server can add one first.
 */
function parseStreamEvent(event: ServerSentEvent): AssistantStreamEvent | null {
    let data: unknown;

    try {
        data = JSON.parse(event.data);
    } catch {
        return null;
    }

    if (typeof data !== 'object' || data === null) {
        return null;
    }

    const payload = data as Record<string, unknown>;

    switch (event.event) {
        case 'turn':
            return typeof payload.conversation_id === 'string' &&
                typeof payload.turn_id === 'string'
                ? {
                      type: 'turn',
                      conversation_id: payload.conversation_id,
                      turn_id: payload.turn_id,
                  }
                : null;
        case 'delta':
            return typeof payload.text === 'string'
                ? { type: 'delta', text: payload.text }
                : null;
        case 'done':
            return {
                type: 'done',
                metrics: payload as unknown as AssistantTurnMetrics,
            };
        case 'error':
            return {
                type: 'error',
                message:
                    typeof payload.message === 'string'
                        ? payload.message
                        : trans('arcadia.assistant.failed'),
            };
        default:
            return null;
    }
}

// What a refused request says: a validation or throttle message from Laravel, or ours.
async function failureMessage(response: Response): Promise<string> {
    if (response.status === 419) {
        return trans('arcadia.assistant.expired');
    }

    if (response.status === 429) {
        return trans('arcadia.assistant.too_many');
    }

    try {
        const body: unknown = await response.json();

        if (
            typeof body === 'object' &&
            body !== null &&
            'message' in body &&
            typeof body.message === 'string' &&
            response.status === 422
        ) {
            return body.message;
        }
    } catch {
        // Not JSON — an HTML error page; the generic message below says enough.
    }

    return trans('arcadia.assistant.failed');
}

// Laravel's CSRF cookie, which Inertia's own client sends for every other request.
function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((each) => each.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

// A key for the list only; `crypto.randomUUID()` would need a secure context, which a dev host
// over plain HTTP is not.
let lastLocalId = 0;

function localId(): string {
    lastLocalId += 1;

    return `local-${lastLocalId}`;
}
