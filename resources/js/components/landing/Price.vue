<script setup>
/**
 * A guide's price as the landing page states it, everywhere it does: the figure, and the owner's
 * short note beside it when there is one (`BCV` — the dollar at Venezuela's central-bank rate),
 * set in the editor's settings. The figure's size is the caller's, through `class`.
 */
defineOptions({ inheritAttrs: false })

defineProps({
    // Already written: `$3`.
    amount: { type: String, required: true },
    note: { type: String, default: null },
})
</script>

<template>
    <span class="inline-flex items-baseline gap-1">
        <span class="font-display font-bold text-ink" v-bind="$attrs">{{ amount }}</span>
        <span v-if="note" class="self-center rounded-sm border border-accent/40 bg-accent/15 px-1 py-px font-mono text-[0.625rem] leading-tight font-semibold tracking-wide text-accent uppercase">{{ note }}</span>
    </span>
</template>
