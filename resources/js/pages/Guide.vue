<script setup>
/**
 * A guide as its reader sees it — opened by its link, or by the owner as a preview of any
 * version. Built only from the reader's own components (`components/reader/`), which read
 * only the `--guide-*` tokens, so a guide can later carry its own colours.
 *
 * Its colours arrive as `appearance` — the `--guide-*` tokens of a palette and an accent, set on
 * the page's root; empty for Arcadia's own. A signed-in reader of this guide also gets
 * `readerAppearance`, and changes their colours here: the page repaints as they pick.
 *
 * The structure is the prototype's: how to use it, then the stages, then going further and tips.
 * A reader's progress is on their account and arrives as `progress` — the keys they ticked and
 * where a tick is saved; without it (the owner, the preview) ticks last for the visit
 * (`useGuideProgress`).
 *
 * A signed-in reader also gets their own notes, on lines and stages (`useGuideNotes`, provided to
 * every line and stage below). On a large screen the guide moves left and the notes stand beside
 * it, in collapsible toggles per stage and line (`NotesAside`); on a phone each line and stage
 * opens its notes in a sheet (`NotesSheet`). The owner and the preview get neither.
 *
 * Stages fold, and for a reader of a guide that unlocks in order (`progress.unlockInOrder`) they
 * open one after another. Nothing about it is stored: a stage is open when it is the first, when
 * the one before it is open and complete, or when it or a later stage already holds a tick — so
 * a later version that adds lines or stages behind a reader never shuts one they were in. The
 * owner and the preview get no flag, and nothing locks. The last stage open to the reader starts
 * expanded, the rest folded; with nothing locked, the first with a line left to do. A stage that
 * opens during the visit unfolds.
 *
 * Opened as `#line-<key>` — "Continue" on "My guides" — the page unfolds that line's stage and
 * scrolls to it, when the stage is open to the reader.
 *
 * A tick that completes a stage in this visit — never on load, so a reload does not replay it —
 * cheers at the stage's end: what the reader can now do (its goal), a phrase from `celebration`
 * (a stage's, or the guide's on the last one) and the way on to the next stage.
 *
 * Viewed by the owner as one reader (`viewingAs`, `Editor\ReaderViewController`): their ticks and
 * locks, read only (`progress.readOnly`) — nothing can be ticked or answered — and of their notes
 * only where there are some (`noteMarks`), never what they say.
 *
 * A reader's first visit to the guide in a session brings a `motivation` phrase, popped up once
 * as a toast. The server sends it once per session; this browser also remembers it showed it, so
 * going Back to the page — which restores the props it had — does not show it twice.
 */
import { computed, nextTick, onMounted, provide, ref, watch } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Eye, Palette } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ReaderColoursSheet from '@/components/ReaderColoursSheet.vue'
import { appearanceTokens } from '@/lib/appearance.js'
import ProgressBar from '@/components/reader/ProgressBar.vue'
import ReaderStage from '@/components/reader/ReaderStage.vue'
import { useGuideProgress } from '@/components/reader/useGuideProgress.js'
import { useGuideNotes } from '@/components/reader/useGuideNotes.js'
import NotesAside from '@/components/reader/NotesAside.vue'
import NotesSheet from '@/components/reader/NotesSheet.vue'
import ReaderGreeting from '@/components/reader/ReaderGreeting.vue'
import FlipDeck from '@/components/reader/FlipDeck.vue'

const props = defineProps({
    guide: { type: Object, required: true },
    version: { type: Number, required: true },
    sections: { type: Array, required: true },
    stages: { type: Array, required: true },
    preview: { type: Boolean, default: false },
    appearance: { type: Object, default: () => ({}) },
    // { palette, accent, saveUrl, options } for a signed-in reader of this guide, else null.
    readerAppearance: { type: Object, default: null },
    // { ticked, saveUrl, unlockInOrder } for a signed-in reader of this guide, else null.
    progress: { type: Object, default: null },
    // { list, storeUrl, maxLength } — the reader's own notes — for a signed-in reader, else null.
    notes: { type: Object, default: null },
    // The reader's own guides, for a signed-in reader.
    myGuidesUrl: { type: String, default: null },
    privacyUrl: { type: String, required: true },
    // What the greeting calls the reader — highlighted — or null.
    readerName: { type: String, default: null },
    // A phrase for where the reader stands on it, on their first visit in a session, else null.
    motivation: { type: String, default: null },
    // { stage, done } — the phrases to cheer a stage, and the whole guide, completed here; else null.
    celebration: { type: Object, default: null },
    // { states, saveUrl } — a reader's answers to the flip cards, and where to save one; else null.
    cards: { type: Object, default: null },
    // Whose view this is, when the owner views the guide as one reader; else null.
    viewingAs: { type: String, default: null },
    // ["item:key", "stage:key"] — where that reader has notes, in the owner's view of them.
    noteMarks: { type: Array, default: () => [] },
    pageTitle: String,
    noIndex: Boolean,
})

// While the reader is picking, the page shows the picks; otherwise what the server sent.
const picking = ref(false)
const palette = ref(props.readerAppearance?.palette ?? 'arcadia')
const accent = ref(props.readerAppearance?.accent ?? 'teal')
const tokens = computed(() => picking.value && props.readerAppearance
    ? appearanceTokens(props.readerAppearance.options, palette.value, accent.value)
    : props.appearance)

const seo = computed(() => ({ pageTitle: props.pageTitle, noIndex: props.noIndex }))

const progress = useGuideProgress(props.progress)
// A line's quick check answers through it.
provide('guideProgress', progress)
// Where a reader has notes, for the owner's view of them: a mark, never the text.
provide('guideNoteMarks', new Set(props.noteMarks))

const notes = useGuideNotes(props.notes)
provide('guideNotes', notes)

// The side panel from `lg` up; below it, the sheet.
const wide = useMediaQuery('(min-width: 1024px)')

// What a line or stage is called, for the sheet's heading.
const titleFor = target => target.kind === 'stage'
    ? props.stages.find(stage => stage.key === target.key)?.title
    : props.stages.flatMap(stage => stage.cards.flatMap(card => card.items)).find(item => item.key === target.key)?.title

const keys = computed(() => props.stages.flatMap(stage => stage.cards.flatMap(card => card.items.map(item => item.key))))

const stageKeys = computed(() => props.stages.map(stage => stage.cards.flatMap(card => card.items.map(item => item.key))))

// Which stages are open to the reader, in order. Only a reader's own page carries the flag.
const unlocked = computed(() => {
    const ordered = Boolean(props.progress?.unlockInOrder)
    const started = stageKeys.value.map(lines => progress.count(lines) > 0)

    return stageKeys.value.reduce((open, lines, index) => {
        const previous = stageKeys.value[index - 1]
        const afterComplete = index === 0 || (open[index - 1] && progress.count(previous) === previous.length)

        return [...open, !ordered || afterComplete || started.slice(index).some(Boolean)]
    }, [])
})

const firstExpanded = () => {
    if (props.progress?.unlockInOrder) {
        return unlocked.value.lastIndexOf(true)
    }

    const unfinished = stageKeys.value.findIndex(lines => progress.count(lines) < lines.length)
    return unfinished === -1 ? stageKeys.value.length - 1 : unfinished
}

const expanded = ref(new Set([props.stages[firstExpanded()]?.key]))

const fold = key => {
    const next = new Set(expanded.value)
    next.has(key) ? next.delete(key) : next.add(key)
    expanded.value = next
}

// A stage opened by a tick in this visit unfolds, ready for its first line.
watch(unlocked, (now, before) => {
    const opened = props.stages.filter((stage, index) => now[index] && !before[index])
    if (opened.length) {
        expanded.value = new Set([...expanded.value, ...opened.map(stage => stage.key)])
    }
})

// The stage a tick just completed here, and the phrase to cheer it with.
const complete = computed(() => stageKeys.value.map(lines => lines.length > 0 && progress.count(lines) === lines.length))
const celebrating = ref(null)

watch(complete, (now, before) => {
    if (celebrating.value && !now[props.stages.findIndex(stage => stage.key === celebrating.value.key)]) {
        celebrating.value = null
    }

    const index = now.findIndex((done, at) => done && !before[at])
    if (index === -1) return

    const guide = progress.count(keys.value) === keys.value.length
    const phrases = (guide ? props.celebration?.done : props.celebration?.stage) ?? []
    celebrating.value = { key: props.stages[index].key, guide, phrase: phrases[Math.floor(Math.random() * phrases.length)] ?? null }
})

// On to a stage: unfolded, and in view.
const goToStage = async index => {
    const stage = props.stages[index]
    if (!stage) return

    expanded.value = new Set([...expanded.value, stage.key])
    await nextTick()
    document.querySelector(`[data-stage-key="${CSS.escape(stage.key)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// A clip's "next": the line after it, unfolding its stage — or nowhere, when that stage is locked.
const lines = computed(() => props.stages.flatMap((stage, index) => stage.cards.flatMap(card => card.items.map(item => ({ key: item.key, stage: index })))))

const goNext = async key => {
    const after = lines.value[lines.value.findIndex(line => line.key === key) + 1]
    if (!after || !unlocked.value[after.stage]) return

    expanded.value = new Set([...expanded.value, props.stages[after.stage].key])
    await nextTick()
    document.querySelector(`[data-guide-line="${CSS.escape(after.key)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => {
    const hash = window.location.hash
    if (hash.startsWith('#line-')) {
        const line = lines.value.find(line => line.key === decodeURIComponent(hash.slice('#line-'.length)))
        // Unfolded now, the line is rendered before Inertia looks for the anchor and scrolls to it.
        if (line && unlocked.value[line.stage]) {
            expanded.value = new Set([...expanded.value, props.stages[line.stage].key])
        }
    }

    if (!props.motivation) return

    // Storage can be blocked or absent (a private window): then the server's once is enough.
    const key = `arcadia:motivated:${window.location.pathname}`
    try {
        if (sessionStorage.getItem(key)) return
        sessionStorage.setItem(key, '1')
    } catch {}

    toast(props.motivation, { duration: 6000 })
})

const before = computed(() => props.sections.filter(section => section.kind === 'how_to_use'))
const after = computed(() => props.sections.filter(section => section.kind !== 'how_to_use'))
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="min-h-screen bg-guide-ground" :style="tokens">
        <div :class="notes.enabled ? 'lg:mx-auto lg:grid lg:max-w-6xl lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-8 lg:px-6' : ''">
            <div class="relative mx-auto max-w-3xl px-4 pb-20 pt-8 sm:px-6 sm:pt-12" :class="notes.enabled ? 'lg:mx-0 lg:w-full lg:px-0' : ''">
                <p v-if="viewingAs" class="mb-6 flex items-center gap-2 rounded-xl border border-guide-accent bg-guide-surface px-4 py-3 text-sm text-guide-ink">
                    <Eye :size="16" class="shrink-0 text-guide-accent" aria-hidden="true" />
                    {{ $t('arcadia.reader.viewing_as', { name: viewingAs }) }}
                </p>
                <p v-if="preview" class="mb-6 flex items-center gap-2 rounded-xl border border-guide-accent bg-guide-surface px-4 py-3 text-sm text-guide-ink">
                    <Eye :size="16" class="shrink-0 text-guide-accent" aria-hidden="true" />
                    {{ $t('arcadia.reader.preview', { number: version }) }}
                </p>

                <!-- The personal opening, before anything else. -->
                <ReaderGreeting v-if="guide.greeting || readerName" :greeting="guide.greeting ?? ''" :name="readerName" :preview="preview" />

                <header>
                    <!-- The cover, the whole frame; its size holds the place before it loads. -->
                    <img v-if="guide.cover" :src="guide.cover.url" :width="guide.cover.width" :height="guide.cover.height" alt=""
                         class="mb-6 block h-auto w-full rounded-2xl border border-guide-rule">
                    <p v-if="guide.category" class="m-0 font-mono text-xs uppercase tracking-widest text-guide-accent">{{ guide.category }}</p>
                    <h1 class="mt-2 mb-0 font-display text-3xl font-bold leading-tight text-guide-ink sm:text-4xl">{{ guide.title }}</h1>
                    <p class="mt-3 mb-0 flex flex-wrap gap-x-4 gap-y-1 text-sm text-guide-ink-soft">
                        <span><span class="text-guide-ink">{{ $t('arcadia.reader.level') }}:</span> {{ guide.level }}</span>
                        <span v-if="guide.goals.length"><span class="text-guide-ink">{{ $t('arcadia.reader.goals') }}:</span> {{ guide.goals.join(' · ') }}</span>
                    </p>

                    <div class="mt-6">
                        <ProgressBar :done="progress.count(keys)" :total="keys.length" />
                        <p v-if="props.progress" class="mt-1 mb-0 text-xs text-guide-ink-soft">{{ $t('arcadia.reader.progress_saved') }}</p>
                    </div>
                </header>

                <section v-for="section in before" :key="section.key" class="mt-8 rounded-2xl bg-guide-well p-5 sm:p-6">
                    <h2 class="m-0 font-display text-lg font-semibold text-guide-ink">{{ section.title || $t(`arcadia.editor.content.section_kinds.${section.kind}`) }}</h2>
                    <p class="mt-2 mb-0 whitespace-pre-line text-guide-ink">{{ section.body }}</p>
                </section>

                <div class="mt-8 flex flex-col gap-6">
                    <ReaderStage v-for="(stage, index) in stages" :key="stage.key" :stage="stage" :number="index + 1" :progress="progress"
                                 :expanded="expanded.has(stage.key)" :locked="!unlocked[index]"
                                 :celebration="celebrating?.key === stage.key ? celebrating : null" :last="index === stages.length - 1"
                                 @fold="fold(stage.key)" @next="goNext" @onward="goToStage(index + 1)" />
                </div>

                <section v-for="section in after" :key="section.key" class="mt-8">
                    <h2 class="m-0 font-display text-lg font-semibold text-guide-ink">{{ section.title || $t(`arcadia.editor.content.section_kinds.${section.kind}`) }}</h2>
                    <!-- "Must master" as flip cards, when it has them; the list stays, folded under them. -->
                    <template v-if="section.activity?.family === 'flip_cards' && section.activity.cards.length">
                        <FlipDeck :cards="section.activity.cards" :saved="cards" />
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-guide-ink-soft hover:text-guide-accent">{{ $t('arcadia.reader.cards.list') }}</summary>
                            <p class="mt-2 mb-0 whitespace-pre-line text-guide-ink-soft">{{ section.body }}</p>
                        </details>
                    </template>
                    <p v-else class="mt-2 mb-0 whitespace-pre-line text-guide-ink-soft">{{ section.body }}</p>
                </section>

                <footer class="mt-16 border-t border-guide-rule pt-6 text-center text-xs text-guide-ink-soft">
                    <a v-if="myGuidesUrl" :href="myGuidesUrl" class="mr-4 text-guide-ink-soft no-underline hover:text-guide-accent">{{ $t('arcadia.my_guides.title') }}</a>
                    <a :href="privacyUrl" class="text-guide-ink-soft no-underline hover:text-guide-accent">{{ $t('arcadia.reader.privacy') }}</a>
                </footer>
            </div>

            <!-- Beside the guide on a large screen, held in view as the guide scrolls. -->
            <NotesAside v-if="notes.enabled && wide" :stages="stages" />
        </div>

        <NotesSheet v-if="notes.enabled" :active="!wide" :title-for="titleFor" :tokens="tokens" />

        <template v-if="readerAppearance">
            <!-- Bottom-left on a large screen, where it does not cover the notes. -->
            <button type="button" class="fixed right-4 bottom-4 z-20 flex items-center gap-2 rounded-full bg-guide-accent px-4 py-3 text-sm font-semibold text-guide-on-accent shadow-lg"
                    :class="notes.enabled ? 'lg:right-auto lg:left-4' : ''"
                    @click="picking = true">
                <Palette :size="16" aria-hidden="true" />
                {{ $t('arcadia.my_guides.colours.action') }}
            </button>

            <ReaderColoursSheet v-model:palette="palette" v-model:accent="accent"
                                :open="picking" :title="guide.title" :options="readerAppearance.options"
                                :save-url="readerAppearance.saveUrl" :saved="readerAppearance"
                                @close="picking = false" />
        </template>
    </div>
</template>
