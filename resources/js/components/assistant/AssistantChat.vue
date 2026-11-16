<script setup lang="ts">
/**
 * The sales assistant's chat: the conversation, the reply as it streams in, and one field to
 * write in. It knows nothing of where it is placed — the page gives it the URL a turn posts to
 * and the context that travels with every message, and hears back through its events. The
 * editor's test screen listens for each turn's metrics; a public placement can ignore them.
 *
 * Replies are plain text: the persona is told not to write markdown, and line breaks are kept.
 */
import { ArrowUp, RotateCcw } from '@lucide/vue';
import { nextTick, ref, useTemplateRef, watch } from 'vue';
import AssistantBubble from '@/components/assistant/AssistantBubble.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAssistantChat } from '@/composables/useAssistantChat';
import type { AssistantChatEmits, AssistantChatProps } from '@/types';

const props = withDefaults(defineProps<AssistantChatProps>(), {
    context: () => ({}),
    greeting: null,
});

const emit = defineEmits<AssistantChatEmits>();

const { messages, status, canSend, send, retry, reset } = useAssistantChat({
    url: () => props.url,
    context: () => props.context,
    onConversationStarted: (conversationId) =>
        emit('conversation-started', conversationId),
    onTurnCompleted: (metrics) => emit('turn-completed', metrics),
});

const draft = ref('');
const log = useTemplateRef<HTMLElement>('log');

async function submit(): Promise<void> {
    const text = draft.value;

    if (!canSend(text)) {
        return;
    }

    draft.value = '';
    await send(text);
}

// Enter sends, Shift+Enter breaks the line; a keyboard still composing an accent is left alone.
function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();
    void submit();
}

function startOver(): void {
    reset();
    draft.value = '';
    emit('reset');
}

// Follow the reply as it grows, unless the reader has scrolled up to reread.
watch(
    () => messages.value.map((message) => message.text.length).join(),
    async () => {
        const element = log.value;

        if (!element) {
            return;
        }

        const isNearBottom =
            element.scrollHeight - element.scrollTop - element.clientHeight <
            120;

        await nextTick();

        if (isNearBottom) {
            element.scrollTop = element.scrollHeight;
        }
    },
);
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-col overflow-hidden rounded-panel border border-rule bg-raised"
    >
        <div
            ref="log"
            role="log"
            aria-live="polite"
            :aria-label="$t('assistant.log_label')"
            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4"
        >
            <AssistantBubble v-if="greeting && messages.length === 0">
                {{ greeting }}
            </AssistantBubble>

            <p
                v-else-if="messages.length === 0"
                class="m-auto max-w-xs text-center text-sm text-ink-3"
            >
                {{ $t('assistant.empty') }}
            </p>

            <template v-for="message in messages" :key="message.id">
                <p
                    v-if="message.role === 'visitor'"
                    class="max-w-[85%] self-end rounded-2xl rounded-tr-sm bg-accent px-4 py-2.5 whitespace-pre-line text-on-accent"
                >
                    {{ message.text }}
                </p>

                <AssistantBubble v-else>
                    <span v-if="message.text !== ''">{{ message.text }}</span>
                    <span
                        v-else
                        class="inline-flex items-center gap-2 text-ink-3"
                    >
                        <Spinner />
                        {{ $t('assistant.typing') }}
                    </span>
                </AssistantBubble>
            </template>

            <div
                v-if="status.state === 'failed'"
                role="alert"
                class="flex flex-wrap items-center gap-3 rounded-control border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger"
            >
                <span class="flex-1">{{ status.message }}</span>
                <Button variant="danger" size="sm" @click="retry">
                    {{ $t('assistant.retry') }}
                </Button>
            </div>
        </div>

        <form
            class="flex items-end gap-2 border-t border-rule p-3"
            @submit.prevent="submit"
        >
            <Button
                v-if="messages.length > 0"
                type="button"
                variant="ghost"
                size="icon"
                :title="$t('assistant.start_over')"
                :aria-label="$t('assistant.start_over')"
                @click="startOver"
            >
                <RotateCcw />
            </Button>
            <textarea
                v-model="draft"
                rows="1"
                :maxlength="props.maxLength"
                :placeholder="$t('assistant.placeholder')"
                :aria-label="$t('assistant.placeholder')"
                class="field-sizing-content max-h-40 min-h-10 flex-1 resize-none rounded-control border-2 border-rule bg-well px-3 py-2 text-ink transition-colors outline-none placeholder:text-ink-4 focus-visible:border-accent"
                @keydown="onKeydown"
            />
            <Button
                type="submit"
                size="icon"
                :disabled="!canSend(draft)"
                :aria-label="$t('assistant.send')"
            >
                <ArrowUp />
            </Button>
        </form>
    </div>
</template>
