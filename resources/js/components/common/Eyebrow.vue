<script setup>
/**
 * The small uppercase label above a heading or a field.
 *
 * It was spelled by hand 61 times in 33 different class strings, at eight letter-spacings and
 * mostly in the body face, although `design-system.md` § Type gives labels to the mono face.
 * Two families survived the variety, and they are the two tones: `accent` names a section,
 * `muted` names a field or a figure. Spacing around it belongs to the caller.
 */
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const props = defineProps({
    tone: { type: String, default: 'accent', validator: value => ['accent', 'muted'].includes(value) },
    size: { type: String, default: 'md', validator: value => ['sm', 'md'].includes(value) },
    as: { type: String, default: 'p' },
    class: { type: null, required: false },
})

const eyebrowVariants = cva('m-0 font-mono uppercase tracking-[0.13em]', {
    variants: {
        tone: { accent: 'text-accent', muted: 'text-ink-3' },
        size: { sm: 'text-[10px]', md: 'text-[11px]' },
    },
})
</script>

<template>
    <component :is="as" :class="cn(eyebrowVariants({ tone, size }), props.class)">
        <slot />
    </component>
</template>
