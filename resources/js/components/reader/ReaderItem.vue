<script setup>
/**
 * One line of the guide: a tick, its title, and what it opens — a video or playlist that plays
 * here on request, a site, or the guide's own words. A line whose source has gone says so and
 * offers nothing broken.
 *
 * A clip's "next", at its end, ticks the line and asks the page for the one after it — which may be
 * in a folded stage, to open, or a locked one, to stop at.
 *
 * For a signed-in reader, its notes: a row under the line — under the video, never over it — with
 * how many there are at one edge and "add a note" at the other. Either opens them: in the side
 * panel on a wide screen, in a sheet on a phone (`useGuideNotes`).
 */
import { computed, inject } from 'vue'
import { Check, ExternalLink, NotebookPen, Plus } from '@lucide/vue'
import VideoEmbed from './VideoEmbed.vue'
import { clipLabel as describeClip } from '@/lib/clock.js'

const props = defineProps({
    item: { type: Object, required: true },
    done: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'next'])

// The clip is watched: done, and on to the next line of the guide, wherever the page finds it.
const next = () => {
    if (!props.done) {
        emit('toggle')
    }

    emit('next')
}

const isYoutube = computed(() => ['youtube_video', 'youtube_playlist'].includes(props.item.kind))
const isLink = computed(() => isYoutube.value || props.item.kind === 'external_site')

// Which part of the video plays, said before the reader commits to it.
const clipLabel = computed(() => describeClip(props.item.clip?.start, props.item.clip?.end))

const notes = inject('guideNotes', null)
const target = computed(() => ({ kind: 'item', key: props.item.key }))
const noteCount = computed(() => notes?.countFor(target.value) ?? 0)
</script>

<template>
    <li :data-guide-line="item.key" class="flex list-none scroll-mt-24 gap-3 py-3">
        <button type="button" role="checkbox" :aria-checked="done" :aria-label="`${$t('arcadia.reader.mark_done')}: ${item.title}`"
                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border-2 transition-colors"
                :class="done ? 'border-guide-done bg-guide-done text-guide-on-accent' : 'border-guide-rule hover:border-guide-accent'"
                @click="$emit('toggle')">
            <Check v-if="done" :size="14" :stroke-width="3" aria-hidden="true" />
        </button>

        <div class="min-w-0 flex-1">
            <p class="m-0 font-medium" :class="done ? 'text-guide-ink-soft line-through decoration-guide-rule' : 'text-guide-ink'">{{ item.title }}</p>
            <p v-if="clipLabel" class="mt-0.5 mb-0 font-mono text-xs text-guide-ink-soft">{{ clipLabel }}</p>
            <p v-if="item.hint" class="mt-0.5 mb-0 text-sm text-guide-ink-soft">{{ item.hint }}</p>
            <p v-if="item.body" class="mt-1 mb-0 whitespace-pre-line text-sm text-guide-ink">{{ item.body }}</p>

            <p v-if="!item.is_available" class="mt-2 mb-0 text-sm text-guide-ink-soft italic">{{ $t('arcadia.reader.unavailable') }}</p>

            <template v-else-if="isLink">
                <VideoEmbed v-if="item.embed" :embed="item.embed" :title="item.title" @next="next" />
                <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer"
                   class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-guide-accent no-underline hover:underline">
                    <ExternalLink :size="14" aria-hidden="true" />
                    {{ isYoutube ? $t('arcadia.reader.open_youtube') : $t('arcadia.reader.open_site') }}
                </a>
            </template>

            <div v-if="notes?.enabled" class="mt-2 flex items-center justify-between gap-3 text-xs">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full py-1 text-guide-ink-soft hover:text-guide-accent"
                        :class="noteCount ? 'font-semibold text-guide-ink' : ''" @click="notes.open(target)">
                    <NotebookPen :size="13" aria-hidden="true" />
                    {{ $t('arcadia.reader.notes.title') }}
                    <span v-if="noteCount" class="rounded-full bg-guide-accent px-1.5 font-mono text-[11px] leading-4 font-semibold text-guide-on-accent">{{ noteCount }}</span>
                </button>
                <button type="button" class="inline-flex items-center gap-1 rounded-full border border-guide-rule px-2.5 py-1 text-guide-ink hover:border-guide-accent"
                        :aria-label="`${$t('arcadia.reader.notes.add')}: ${item.title}`" @click="notes.open(target)">
                    <Plus :size="13" aria-hidden="true" />
                    {{ $t('arcadia.reader.notes.add_short') }}
                </button>
            </div>
        </div>
    </li>
</template>
