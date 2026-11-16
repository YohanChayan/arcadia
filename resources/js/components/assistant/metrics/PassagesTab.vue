<script setup lang="ts">
/** What the retriever handed the assistant for one turn, best match first. */
import type { PassagesTabProps } from '@/types';

defineProps<PassagesTabProps>();
</script>

<template>
    <p v-if="passages.length === 0" class="text-sm text-ink-3">
        {{ $t('editor.assistant.no_passages') }}
    </p>
    <ol v-else class="flex flex-col gap-3">
        <li
            v-for="(passage, index) in passages"
            :key="index"
            class="rounded-control border border-rule p-3"
        >
            <div class="mb-1 flex items-baseline justify-between gap-3">
                <span class="font-medium text-ink">
                    {{ passage.guide_title }}
                    <span class="font-mono text-xs text-ink-4"
                        >#{{ passage.guide_id }}</span
                    >
                </span>
                <span class="shrink-0 font-mono text-xs text-ink-3"
                    >{{ passage.score.toFixed(2) }} · {{ passage.source }}</span
                >
            </div>
            <p class="text-sm text-ink-2">
                {{ passage.content }}
            </p>
        </li>
    </ol>
</template>
