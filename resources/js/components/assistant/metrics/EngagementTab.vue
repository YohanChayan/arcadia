<script setup lang="ts">
/** How engaged the visitor read at each turn of the conversation, 0–100. */
import { turnLabel } from '@/lib/assistant-format';
import type { EngagementTabProps } from '@/types';

defineProps<EngagementTabProps>();
</script>

<template>
    <p class="mb-3 text-sm text-ink-3">
        {{ $t('editor.assistant.engagement_lead') }}
    </p>
    <ol class="flex flex-col gap-2">
        <li
            v-for="(each, index) in turns"
            :key="index"
            class="grid grid-cols-[4.5rem_1fr_2.5rem] items-center gap-3 text-sm"
        >
            <span class="text-ink-3">{{ turnLabel(index + 1) }}</span>
            <span class="h-2 overflow-hidden rounded-full bg-well">
                <span
                    class="block h-full rounded-full bg-accent"
                    :style="{ width: `${each.engagement}%` }"
                />
            </span>
            <span class="text-right font-mono text-ink">{{
                each.engagement
            }}</span>
        </li>
    </ol>
</template>
