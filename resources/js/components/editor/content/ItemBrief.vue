<script setup>
/**
 * What a linked line teaches, for the owner (`guide_items.brief`, written by the research AI):
 * the summary, the topics it covers, and who made it, how long, at what level, in what language.
 * Never shown to a reader.
 *
 * It is also the way to a replacement — for a line whose source has gone, or one the owner judges
 * poor on review: a YouTube search with its own words, and a short prompt, ready to paste into an
 * AI, asking for another free resource that covers the same topics, short, never the same link.
 */
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Search } from '@lucide/vue'
import CopyButton from '@/components/common/CopyButton.vue'

const props = defineProps({
    item: { type: Object, required: true },
})

const content = inject('content')

const brief = computed(() => props.item.brief ?? {})

const meta = computed(() => [brief.value.creator, brief.value.duration, brief.value.level, brief.value.language?.toUpperCase()]
    .filter(Boolean).join(' · '))

const searchUrl = computed(() => `https://www.youtube.com/results?${new URLSearchParams({ search_query: brief.value.search || props.item.title })}`)

const replacementPrompt = computed(() => trans('arcadia.editor.content.brief.prompt', {
    title: props.item.title,
    summary: brief.value.summary ?? '—',
    covers: (brief.value.covers ?? []).join(', ') || '—',
    level: brief.value.level ?? '—',
    language: brief.value.language ?? '—',
    duration: brief.value.duration ?? '—',
    url: props.item.url ?? '—',
    minutes: content.stepLength,
}))
</script>

<template>
    <div class="rounded-control border border-rule bg-well/50 px-3 py-2.5 text-xs text-ink-2">
        <p v-if="brief.summary" class="m-0 text-sm text-ink-2">{{ brief.summary }}</p>
        <ul v-if="brief.covers?.length" class="m-0 mt-2 flex flex-wrap gap-1.5 p-0">
            <li v-for="topic in brief.covers" :key="topic" class="list-none rounded-full border border-rule px-2 py-0.5 text-ink-2">{{ topic }}</li>
        </ul>
        <p v-if="meta" class="mt-2 mb-0 font-mono text-[11px] text-ink-4">{{ meta }}</p>

        <div class="mt-2.5 flex flex-wrap items-center gap-2">
            <a :href="searchUrl" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 rounded-full border border-rule px-2.5 py-1 font-medium text-ink no-underline hover:border-accent hover:text-accent">
                <Search :size="12" aria-hidden="true" />
                {{ $t('arcadia.editor.content.brief.search') }}
            </a>
            <CopyButton :value="replacementPrompt" :label="$t('arcadia.editor.content.brief.copy_prompt')" :copied-label="$t('arcadia.editor.content.brief.copied')" />
        </div>
    </div>
</template>
