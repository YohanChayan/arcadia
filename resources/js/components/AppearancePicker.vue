<script setup>
/**
 * A guide's colours, picked: one palette, one accent, from the curated sets the server offers
 * (`Appearance::options()`). Only these, so no pick can make a guide unreadable.
 *
 * Shared by the owner's editor and, later, the reader's own controls. It writes nothing — it
 * only moves the two v-models; whoever holds them previews and saves.
 */
import { Check } from '@lucide/vue'

defineProps({
    // { palettes: [{ value, label, colours }], accents: [{ value, label, hex }] }
    options: { type: Object, required: true },
    name: { type: String, default: 'appearance' },
})

const palette = defineModel('palette', { type: String, required: true })
const accent = defineModel('accent', { type: String, required: true })
</script>

<template>
    <div class="grid gap-5">
        <fieldset class="m-0 min-w-0 border-0 p-0">
            <legend class="mb-2 text-sm font-semibold text-ink">{{ $t('arcadia.appearance.palette') }}</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <label v-for="option in options.palettes" :key="option.value"
                       class="relative flex cursor-pointer items-center gap-2.5 rounded-control border p-2 text-sm text-ink-2 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-focus"
                       :class="palette === option.value ? 'border-accent bg-accent/10 text-ink' : 'border-rule hover:border-ink-4'">
                    <input v-model="palette" type="radio" :name="`${name}-palette`" :value="option.value" class="sr-only" />
                    <span class="flex h-8 w-10 shrink-0 overflow-hidden rounded-md border border-rule" aria-hidden="true">
                        <span class="h-full w-1/2" :style="{ background: option.colours.ground }"></span>
                        <span class="flex h-full w-1/2 items-center justify-center" :style="{ background: option.colours.surface }">
                            <span class="h-1 w-3 rounded-full" :style="{ background: option.colours.ink }"></span>
                        </span>
                    </span>
                    <span class="truncate">{{ option.label }}</span>
                    <Check v-if="palette === option.value" :size="14" class="ml-auto shrink-0 text-accent" aria-hidden="true" />
                </label>
            </div>
        </fieldset>

        <fieldset class="m-0 min-w-0 border-0 p-0">
            <legend class="mb-2 text-sm font-semibold text-ink">{{ $t('arcadia.appearance.accent') }}</legend>
            <div class="flex flex-wrap gap-2">
                <label v-for="option in options.accents" :key="option.value" :title="option.label"
                       class="relative flex size-11 cursor-pointer items-center justify-center rounded-full border-2 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-focus"
                       :class="accent === option.value ? 'border-ink' : 'border-transparent'">
                    <input v-model="accent" type="radio" :name="`${name}-accent`" :value="option.value" class="sr-only" />
                    <span class="sr-only">{{ option.label }}</span>
                    <span class="flex size-8 items-center justify-center rounded-full" :style="{ background: option.hex }" aria-hidden="true">
                        <Check v-if="accent === option.value" :size="16" class="text-on-accent" />
                    </span>
                </label>
            </div>
        </fieldset>
    </div>
</template>
