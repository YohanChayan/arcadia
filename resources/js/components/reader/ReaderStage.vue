<script setup>
/**
 * One stage: its title, what the reader can do when they finish it, its progress, and its cards.
 * The final challenge is a card too, so its tasks count toward the stage like any other line.
 */
import { computed, inject } from 'vue'
import { NotebookPen } from '@lucide/vue'
import ProgressBar from './ProgressBar.vue'
import ReaderItem from './ReaderItem.vue'

const props = defineProps({
    stage: { type: Object, required: true },
    number: { type: Number, required: true },
    progress: { type: Object, required: true },
})

const keys = computed(() => props.stage.cards.flatMap(card => card.items.map(item => item.key)))
const done = computed(() => props.progress.count(keys.value))

// The stage's own notes, for a signed-in reader: a button at the header's edge.
const notes = inject('guideNotes', null)
const target = computed(() => ({ kind: 'stage', key: props.stage.key }))
const noteCount = computed(() => notes?.countFor(target.value) ?? 0)
</script>

<template>
    <section :data-stage-key="stage.key" class="rounded-2xl border border-guide-rule bg-guide-surface p-5 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <p class="m-0 font-mono text-xs font-medium uppercase tracking-widest text-guide-accent">{{ $t('arcadia.reader.stage', { number }) }}</p>
            <button v-if="notes?.enabled" type="button"
                    class="-mt-1 inline-flex shrink-0 items-center gap-1.5 rounded-full border border-guide-rule px-2.5 py-1 text-xs text-guide-ink hover:border-guide-accent"
                    :aria-label="`${$t('arcadia.reader.notes.on_stage')}: ${stage.title}`" @click="notes.open(target)">
                <NotebookPen :size="13" aria-hidden="true" />
                {{ $t('arcadia.reader.notes.title') }}
                <!-- How many, only when there are any. -->
                <span v-if="noteCount" class="rounded-full bg-guide-accent px-1.5 font-mono text-[11px] leading-4 font-semibold text-guide-on-accent">{{ noteCount }}</span>
            </button>
        </div>
        <h2 class="mt-1 mb-0 font-display text-xl font-semibold text-guide-ink sm:text-2xl">{{ stage.title }}</h2>
        <p v-if="stage.goal" class="mt-1 mb-0 text-sm text-guide-ink-soft">
            <span class="font-medium text-guide-ink">{{ $t('arcadia.reader.when_done') }}</span> {{ stage.goal }}
        </p>

        <div class="mt-4">
            <ProgressBar :done="done" :total="keys.length" />
            <p v-if="keys.length && done === keys.length" class="mt-2 mb-0 text-sm font-medium text-guide-done">{{ $t('arcadia.reader.stage_complete') }}</p>
        </div>

        <article v-for="card in stage.cards" :key="card.key" class="mt-6 rounded-xl p-4 sm:p-5"
                 :class="card.kind === 'challenge' ? 'border-2 border-guide-accent' : 'bg-guide-well'">
            <p v-if="card.kind === 'challenge'" class="m-0 text-xs font-semibold uppercase tracking-wide text-guide-accent">{{ $t('arcadia.reader.challenge') }}</p>
            <p v-if="card.byline" class="m-0 font-semibold text-guide-ink">{{ card.byline }}</p>
            <p v-if="card.description" class="mt-1 mb-0 text-sm text-guide-ink-soft">{{ card.description }}</p>
            <blockquote v-if="card.quote" class="mt-3 mb-0 border-l-2 border-guide-accent pl-3 text-sm italic text-guide-ink-soft">{{ card.quote }}</blockquote>

            <ul class="mt-2 mb-0 divide-y divide-guide-rule p-0">
                <ReaderItem v-for="item in card.items" :key="item.key" :item="item"
                            :done="progress.isDone(item.key)" @toggle="progress.toggle(item.key)" />
            </ul>

            <p v-if="card.closing_note" class="mt-3 mb-0 whitespace-pre-line text-sm text-guide-ink-soft">{{ card.closing_note }}</p>
        </article>
    </section>
</template>
