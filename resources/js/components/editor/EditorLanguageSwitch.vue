<script setup>
/**
 * Which language's editor this is — `/es/editor` or `/en/editor` — and the way to the other, at
 * the top of the editor's sidebar. The current language is filled in its own colour, so a glance
 * says which one you are in: Spanish warm (`--signal`), English blue (`--accent`).
 *
 * Switching keeps the screen: the guides list goes to the guides list, settings to settings, a
 * prompt to the same prompt (prompts are shared by both languages). A guide belongs to one
 * language only, so its own screens fall back to the other language's guides list.
 *
 * It replaced a nav item, "Editor en inglés", on 2026-10-14: a link that looked like any other
 * section, said nothing about where you were, and gave no obvious way back.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const LANGUAGES = ['es', 'en']

const TONES = {
    es: 'border-signal/40 bg-signal/15 text-signal',
    en: 'border-accent/40 bg-accent/15 text-accent',
}

const page = usePage()
const current = computed(() => page.props.locale)

const hrefFor = locale => {
    const screen = page.url.split('?')[0]
        .replace(/^\/(es|en)\/editor/, '')
        .replace(/^\/guides\/\d+(\/.*)?$/, '/guides')

    return `/${locale}/editor${screen}`
}
</script>

<template>
    <div class="space-y-2">
        <p class="m-0 px-3 text-[11px] font-semibold uppercase tracking-wider text-ink-4">{{ $t('arcadia.editor.language.label') }}</p>
        <div role="group" :aria-label="$t('arcadia.editor.language.label')" class="grid grid-cols-2 gap-1 rounded-control border border-rule p-1">
            <Link v-for="locale in LANGUAGES" :key="locale" :href="hrefFor(locale)" :lang="locale"
                  :aria-current="locale === current ? 'page' : undefined"
                  class="flex items-center justify-center gap-2 rounded-control border px-2 py-1.5 text-sm font-medium no-underline transition-colors"
                  :class="locale === current ? TONES[locale] : 'border-transparent text-ink-3 hover:bg-ink/5 hover:text-ink'">
                <span class="font-mono text-xs font-bold">{{ locale.toUpperCase() }}</span>
                <span>{{ $t(`arcadia.editor.language.names.${locale}`) }}</span>
            </Link>
        </div>
    </div>
</template>
