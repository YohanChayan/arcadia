<script setup lang="ts">
/**
 * One thing the assistant says in the chat — its avatar and the bubble beside it. The words go
 * in the slot: a greeting, a reply, or the "typing" state while the reply has not begun.
 */
import { Sparkles } from '@lucide/vue';
</script>

<template>
    <div class="flex items-start gap-2">
        <span
            class="mt-1 grid size-7 shrink-0 place-items-center rounded-full bg-accent/15 text-accent"
        >
            <Sparkles class="size-4" />
        </span>
        <p
            class="max-w-[85%] rounded-2xl rounded-tl-sm bg-well px-4 py-2.5 whitespace-pre-line text-ink"
        >
            <slot />
        </p>
    </div>
</template>
