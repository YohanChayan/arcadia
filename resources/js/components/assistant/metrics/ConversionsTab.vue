<script setup lang="ts">
/** Every guide the visitor accepted in the conversation, with the turn that made the sale. */
import { BadgeCheck } from '@lucide/vue';
import { turnLabel } from '@/lib/assistant-format';
import type { ConversionsTabProps } from '@/types';

defineProps<ConversionsTabProps>();
</script>

<template>
    <p v-if="conversions.length === 0" class="text-sm text-ink-3">
        {{ $t('editor.assistant.no_conversions') }}
    </p>
    <ul v-else class="flex flex-col gap-2">
        <li
            v-for="conversion in conversions"
            :key="conversion.guide_id"
            class="flex items-center justify-between gap-3 rounded-control border border-success/25 bg-success/5 px-3 py-2 text-sm"
        >
            <span class="flex items-center gap-2 text-ink">
                <BadgeCheck class="size-4 text-success" />
                {{ conversion.guide_title }}
                <span class="font-mono text-xs text-ink-4"
                    >#{{ conversion.guide_id }}</span
                >
            </span>
            <span class="text-xs text-ink-3">{{
                turnLabel(conversion.turn)
            }}</span>
        </li>
    </ul>
</template>
