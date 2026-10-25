<script setup>
/**
 * Title, one line of context, and the screen's primary action — for any signed-in surface.
 *
 * It replaced `admin/AdminPageHeader`, the same idea filed under one surface with its count
 * drawn by the `.chip` class. The count is a `Badge` now, and an optional eyebrow names the
 * section above the title.
 */
import { Badge } from '@/components/ui/badge'
import Eyebrow from '@/components/common/Eyebrow.vue'

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    count: { type: [Number, String], default: null },
})
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div class="min-w-0">
            <Eyebrow v-if="eyebrow" class="mb-2">{{ eyebrow }}</Eyebrow>
            <div class="flex items-center gap-3">
                <h1 class="font-display text-2xl sm:text-3xl font-bold text-ink">{{ title }}</h1>
                <Badge v-if="count !== null" variant="secondary" class="font-mono">{{ count }}</Badge>
            </div>
            <p v-if="description" class="mt-1.5 text-sm text-ink-3 max-w-prose">{{ description }}</p>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
