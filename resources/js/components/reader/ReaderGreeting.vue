<script setup>
/**
 * The guide's personal opening, with its reader's name — highlighted, so the guide reads as made
 * for them. The name goes where the greeting asks for it (`{nombre}` or `{name}`); a greeting
 * that does not ask opens with "¡Hola, María!" instead.
 *
 * Without a name — a reader the owner did not name and whose account has none — the placeholder
 * is taken out and the sentence closed up around it. In the owner's preview with no reader
 * chosen, it shows as a marked slot, so they see where a name will go.
 */
import { computed } from 'vue'

const props = defineProps({
    greeting: { type: String, default: '' },
    name: { type: String, default: null },
    preview: { type: Boolean, default: false },
})

// Two patterns, not one: a global one keeps its place between `test()` calls.
const ASKS = /\{(?:nombre|name)\}/i
const PLACEHOLDER = /\{(?:nombre|name)\}/gi

const asksForName = computed(() => ASKS.test(props.greeting ?? ''))

// The greeting cut at each placeholder: text, then a name slot, then text…
const parts = computed(() => {
    const text = props.greeting ?? ''
    if (!asksForName.value) return [{ text }]

    if (!props.name && !props.preview) {
        // No name to put there: closed up, so "Hola {nombre}, esta…" reads "Hola, esta…".
        return [{ text: text.replace(PLACEHOLDER, '').replace(/\s+([,.!?;:])/g, '$1').replace(/ {2,}/g, ' ').replace(/^[\s,]+/, '') }]
    }

    return text.split(PLACEHOLDER).flatMap((piece, index) => (index === 0 ? [{ text: piece }] : [{ name: true }, { text: piece }]))
})
</script>

<template>
    <div class="mt-0 mb-8 rounded-2xl bg-guide-well p-5 sm:p-6">
        <!-- Opened by name when the greeting does not place it itself. -->
        <p v-if="name && !asksForName" class="m-0 mb-2 font-display text-2xl font-bold text-guide-ink sm:text-3xl">
            {{ $t('arcadia.reader.hello_before') }}<span class="name">{{ name }}</span>{{ $t('arcadia.reader.hello_after') }}
        </p>
        <p v-if="greeting" class="m-0 text-lg leading-relaxed whitespace-pre-line text-guide-ink">
            <template v-for="(part, index) in parts" :key="index">
                <span v-if="part.name && name" class="name">{{ name }}</span>
                <span v-else-if="part.name" class="rounded border border-dashed border-guide-accent px-1.5 text-base text-guide-accent">{{ $t('arcadia.reader.name_slot') }}</span>
                <template v-else>{{ part.text }}</template>
            </template>
        </p>
    </div>
</template>

<style scoped>
/* The reader's name: in the accent, on a soft wash of it, so it is the first thing seen. */
.name {
    color: var(--color-guide-accent);
    font-weight: 700;
    background: color-mix(in oklab, var(--color-guide-accent) 16%, transparent);
    border-radius: 0.375rem;
    padding: 0 0.3em;
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}
</style>
