<script setup>
/**
 * One stage: its title, what the reader can do when they finish it, its progress, and its cards.
 * The final challenge is a card too, so its tasks count toward the stage like any other line.
 *
 * It folds: the header opens and closes it, and the page decides which starts open. A stage
 * locked to the reader (`locked`, worked out by the page from the stage order and their ticks)
 * still opens, to show what is coming — its lines, how many and roughly how long — with the stage
 * to finish first. The whole stage sits under a light black layer, its lines under a darker one
 * that takes no clicks.
 *
 * When a tick completes it in this visit, the page passes a `celebration` and the stage ends in a
 * card: complete, what the reader can now do, a phrase, and the way on to the next stage.
 */
import { computed, inject, nextTick, ref, watch } from 'vue'
import { ChevronDown, Lock, NotebookPen, PartyPopper } from '@lucide/vue'
import ProgressBar from './ProgressBar.vue'
import ReaderItem from './ReaderItem.vue'

const props = defineProps({
    stage: { type: Object, required: true },
    number: { type: Number, required: true },
    progress: { type: Object, required: true },
    expanded: { type: Boolean, default: true },
    locked: { type: Boolean, default: false },
    // { guide, phrase } when a tick just completed it, else null.
    celebration: { type: Object, default: null },
    last: { type: Boolean, default: false },
})

defineEmits(['fold', 'next', 'onward'])

const items = computed(() => props.stage.cards.flatMap(card => card.items))
const keys = computed(() => items.value.map(item => item.key))
const done = computed(() => props.progress.count(keys.value))

// Roughly how long it takes: the lines that say, added up; null when none does.
const minutes = computed(() => {
    const known = items.value.filter(item => item.minutes)
    return known.length ? known.reduce((sum, item) => sum + item.minutes, 0) : null
})

// The stage's own notes, for a signed-in reader: a button at the header's edge.
const noteMarks = inject('guideNoteMarks', new Set())

const notes = inject('guideNotes', null)
const target = computed(() => ({ kind: 'stage', key: props.stage.key }))
const noteCount = computed(() => notes?.countFor(target.value) ?? 0)

const bodyId = computed(() => `stage-${props.stage.key}`)

// The cheer comes into view as it appears, under the line that completed the stage.
const cheer = ref(null)
watch(() => props.celebration, async celebration => {
    if (!celebration) return
    await nextTick()
    cheer.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
})
</script>

<template>
    <section :data-stage-key="stage.key" class="relative rounded-2xl border border-guide-rule bg-guide-surface p-5 sm:p-7">
        <div class="flex items-start justify-between gap-3">
            <button type="button" class="-m-1 flex min-w-0 flex-1 items-start gap-3 rounded-lg p-1 text-left"
                    :aria-expanded="expanded" :aria-controls="bodyId" @click="$emit('fold')">
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 font-mono text-xs font-medium uppercase tracking-widest text-guide-accent">
                        {{ $t('arcadia.reader.stage', { number }) }}
                        <span v-if="locked" class="inline-flex items-center gap-1 normal-case tracking-normal text-guide-ink-soft">
                            <Lock :size="12" aria-hidden="true" />
                            {{ $t('arcadia.reader.locked') }}
                        </span>
                        <span v-else-if="keys.length" class="normal-case tracking-normal text-guide-ink-soft">{{ done }}/{{ keys.length }}</span>
                        <!-- The owner's view of a reader: they wrote a note on this stage. -->
                        <NotebookPen v-if="noteMarks.has(`stage:${stage.key}`)" :size="12" class="text-guide-accent" :aria-label="$t('arcadia.reader.has_notes')" />
                    </span>
                    <span class="mt-1 block font-display text-xl font-semibold text-guide-ink sm:text-2xl">{{ stage.title }}</span>
                </span>
                <ChevronDown :size="20" class="mt-5 shrink-0 text-guide-ink-soft transition-transform" :class="expanded ? 'rotate-180' : ''" aria-hidden="true" />
            </button>
            <button v-if="notes?.enabled && !locked" type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-guide-rule px-2.5 py-1 text-xs text-guide-ink hover:border-guide-accent"
                    :aria-label="`${$t('arcadia.reader.notes.on_stage')}: ${stage.title}`" @click="notes.open(target)">
                <NotebookPen :size="13" aria-hidden="true" />
                {{ $t('arcadia.reader.notes.title') }}
                <!-- How many, only when there are any. -->
                <span v-if="noteCount" class="rounded-full bg-guide-accent px-1.5 font-mono text-[11px] leading-4 font-semibold text-guide-on-accent">{{ noteCount }}</span>
            </button>
        </div>

        <div v-if="expanded" :id="bodyId">
            <p v-if="stage.goal" class="mt-2 mb-0 text-sm text-guide-ink-soft">
                <span class="font-medium text-guide-ink">{{ $t('arcadia.reader.when_done') }}</span> {{ stage.goal }}
            </p>

            <!-- What is coming, and what opens it. -->
            <div v-if="locked" class="mt-4 flex items-start gap-3 rounded-xl border border-guide-rule bg-guide-well p-4">
                <Lock :size="18" class="mt-0.5 shrink-0 text-guide-accent" aria-hidden="true" />
                <div class="min-w-0 text-sm">
                    <p class="m-0 font-semibold text-guide-ink">{{ $t('arcadia.reader.unlock_hint', { number: number - 1 }) }}</p>
                    <p class="mt-0.5 mb-0 text-guide-ink-soft">
                        {{ $tChoice('arcadia.reader.stage_lines', keys.length, { count: keys.length }) }}<template v-if="minutes"> · {{ $t('arcadia.reader.stage_minutes', { minutes }) }}</template>
                    </p>
                </div>
            </div>

            <div v-else class="mt-4">
                <ProgressBar :done="done" :total="keys.length" />
                <p v-if="keys.length && done === keys.length" class="mt-2 mb-0 text-sm font-medium text-guide-done">{{ $t('arcadia.reader.stage_complete') }}</p>
            </div>

            <!-- Locked: seen, not used — dimmed, and out of reach of clicks, keys and readers. -->
            <div class="relative" :inert="locked || undefined" :aria-hidden="locked || undefined">
                <article v-for="card in stage.cards" :key="card.key" class="mt-6 rounded-xl p-4 sm:p-5"
                         :class="card.kind === 'challenge' ? 'border-2 border-guide-accent' : 'bg-guide-well'">
                    <p v-if="card.kind === 'challenge'" class="m-0 text-xs font-semibold uppercase tracking-wide text-guide-accent">{{ $t('arcadia.reader.challenge') }}</p>
                    <p v-if="card.byline" class="m-0 font-semibold text-guide-ink">{{ card.byline }}</p>
                    <p v-if="card.description" class="mt-1 mb-0 text-sm text-guide-ink-soft">{{ card.description }}</p>
                    <blockquote v-if="card.quote" class="mt-3 mb-0 border-l-2 border-guide-accent pl-3 text-sm italic text-guide-ink-soft">{{ card.quote }}</blockquote>

                    <ul class="mt-2 mb-0 divide-y divide-guide-rule p-0">
                        <ReaderItem v-for="item in card.items" :key="item.key" :item="item" :locked="locked"
                                    :done="progress.isDone(item.key)" @toggle="progress.toggle(item.key)" @next="$emit('next', item.key)" />
                    </ul>

                    <p v-if="card.closing_note" class="mt-3 mb-0 whitespace-pre-line text-sm text-guide-ink-soft">{{ card.closing_note }}</p>
                </article>

                <div v-if="locked" class="absolute inset-0 rounded-xl bg-black/40" aria-hidden="true"></div>
            </div>

            <div v-if="celebration" ref="cheer" role="status"
                 class="stage-cheer mt-6 rounded-xl border-2 border-guide-done bg-guide-well p-5 text-center sm:p-6">
                <PartyPopper :size="32" class="mx-auto text-guide-done" aria-hidden="true" />
                <p class="mt-2 mb-0 font-display text-xl font-semibold text-guide-ink">
                    {{ $t(celebration.guide ? 'arcadia.reader.guide_complete' : 'arcadia.reader.stage_complete') }}
                </p>
                <p v-if="stage.goal" class="mt-2 mb-0 text-guide-ink">
                    <span class="font-semibold">{{ $t('arcadia.reader.you_can_now') }}</span> {{ stage.goal }}
                </p>
                <p v-if="celebration.phrase" class="mt-2 mb-0 text-sm font-semibold text-guide-accent">{{ celebration.phrase }}</p>
                <button v-if="!last" type="button" @click="$emit('onward')"
                        class="mt-4 inline-flex items-center justify-center rounded-full bg-guide-accent px-5 py-2.5 text-sm font-semibold text-guide-on-accent">
                    {{ $t('arcadia.reader.next_stage', { number: number + 1 }) }}
                </button>
            </div>
        </div>

        <!-- A locked stage, folded or open, sits under a light black layer. It takes no clicks, so
             the header still opens it. Black whatever the reader's colours: the one literal here. -->
        <div v-if="locked" class="pointer-events-none absolute inset-0 rounded-2xl bg-black/30" aria-hidden="true"></div>
    </section>
</template>
