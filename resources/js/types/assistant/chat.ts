/**
 * The chat component's contract with the page that places it, and the composable behind it.
 */
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import type { AssistantTurnMetrics } from './metrics';
import type { AssistantChatStatus, AssistantMessage } from './stream';

export type AssistantChatProps = {
    url: string;
    // The longest message the server accepts (`AssistantMessageRequest::MAX_LENGTH`).
    maxLength: number;
    context?: Record<string, string>;
    // The assistant's opening line, shown before anything is sent. It is never posted.
    greeting?: string | null;
};

export type AssistantChatEmits = {
    'conversation-started': [conversationId: string];
    'turn-completed': [metrics: AssistantTurnMetrics];
    reset: [];
};

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
    // Whether `text` would be sent: not blank, and no reply under way.
    canSend: (text: string) => boolean;
    isReplying: ComputedRef<boolean>;
    send: (text: string) => Promise<void>;
    retry: () => Promise<void>;
    reset: () => void;
};
