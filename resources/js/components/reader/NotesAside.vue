<script setup>
/**
 * The reader's notes beside the guide, on a wide screen: one collapsible toggle per stage, and
 * inside it the stage's own notes and a toggle for each of its lines — a count on every toggle,
 * so where the notes are shows at a glance. A stage or line with notes starts open.
 *
 * It follows the reader without a sound: held beside the guide as the page scrolls, and scrolled
 * — on its own, never the page — to the stage being read, whether that stage is open or not.
 * When a line or a stage asks for its notes (`notes.open`), its toggles open, it comes into view
 * here, and the new-note box takes the cursor — the page itself never moves.
 */
import { inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ChevronDown, NotebookPen } from '@lucide/vue'
import NoteThread from './NoteThread.vue'

const props = defineProps({
    stages: { type: Array, required: true },
})

const notes = inject('guideNotes')

const stageTarget = stage => ({ kind: 'stage', key: stage.key })
const itemTarget = item => ({ kind: 'item', key: item.key })
const itemsOf = stage => stage.cards.flatMap(card => card.items)

const countInStage = stage => notes.countFor(stageTarget(stage))
    + itemsOf(stage).reduce((sum, item) => sum + notes.countFor(itemTarget(item)), 0)

// What is open: stage and line ids. Whatever already has notes starts open.
const expanded = ref(new Set(props.stages.flatMap(stage => [
    ...(countInStage(stage) ? [notes.idOf(stageTarget(stage))] : []),
    ...itemsOf(stage).filter(item => notes.countFor(itemTarget(item))).map(item => notes.idOf(itemTarget(item))),
])))

const isOpen = id => expanded.value.has(id)
const toggle = id => {
    const next = new Set(expanded.value)
    next.has(id) ? next.delete(id) : next.add(id)
    expanded.value = next
}

// The target last asked for: it gets the cursor.
const focused = ref(null)
// The panel's own scroller: everything scrolls here, never the page.
const scroller = ref(null)

const scrollPanelTo = selector => {
    const box = scroller.value
    const el = box?.querySelector(selector)
    if (!box || !el) return

    const top = el.getBoundingClientRect().top - box.getBoundingClientRect().top + box.scrollTop - 12
    box.scrollTo({ top, behavior: 'smooth' })
}

// The stage being read, as the page scrolls.
const reading = ref(props.stages[0]?.key ?? null)
// After the reader asks for a note, the panel stays put a moment rather than following the page.
let heldUntil = 0
let observer = null

onMounted(() => {
    observer = new IntersectionObserver(entries => {
        const seen = entries.filter(entry => entry.isIntersecting)
        if (!seen.length) return

        reading.value = seen[0].target.dataset.stageKey
        if (Date.now() > heldUntil) scrollPanelTo(`[data-panel-stage="${reading.value}"]`)
    }, { rootMargin: '-35% 0px -60% 0px' })

    document.querySelectorAll('[data-stage-key]').forEach(stage => observer.observe(stage))
})
onBeforeUnmount(() => observer?.disconnect())

watch(notes.openedAt, async () => {
    const target = notes.opened.value
    if (!target) return

    const stage = props.stages.find(each => each.key === target.key
        || itemsOf(each).some(item => target.kind === 'item' && item.key === target.key))
    if (!stage) return

    expanded.value = new Set([...expanded.value, notes.idOf(stageTarget(stage)), notes.idOf(target)])
    heldUntil = Date.now() + 1500
    focused.value = null
    await nextTick()
    focused.value = notes.idOf(target)
    await nextTick()
    scrollPanelTo(`[data-note-target="${notes.idOf(target)}"]`)
})
</script>

<template>
    <div ref="scroller" class="sticky top-0 max-h-screen self-start overflow-y-auto overscroll-contain pt-12 pb-8">
        <aside class="rounded-2xl border border-guide-rule bg-guide-surface p-4" :aria-label="$t('arcadia.reader.notes.title')">
            <h2 class="m-0 flex items-center gap-2 font-display text-base font-semibold text-guide-ink">
                <NotebookPen :size="16" class="text-guide-accent" aria-hidden="true" />
                {{ $t('arcadia.reader.notes.title') }}
                <span class="ml-auto font-mono text-xs font-normal text-guide-ink-soft">{{ notes.total.value }}</span>
            </h2>
            <p class="mt-1 mb-0 text-xs text-guide-ink-soft">{{ $t('arcadia.reader.notes.hint') }}</p>

            <div class="mt-3 flex flex-col gap-2">
                <section v-for="(stage, index) in stages" :key="stage.key" :data-panel-stage="stage.key"
                         class="rounded-xl border transition-colors" :class="reading === stage.key ? 'border-guide-accent' : 'border-guide-rule'">
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-2.5 text-left"
                            :aria-expanded="isOpen(notes.idOf(stageTarget(stage)))" @click="toggle(notes.idOf(stageTarget(stage)))">
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono text-[10px] uppercase tracking-widest text-guide-accent">{{ $t('arcadia.reader.stage', { number: index + 1 }) }}</span>
                            <span class="block truncate text-sm font-semibold text-guide-ink">{{ stage.title }}</span>
                        </span>
                        <span v-if="countInStage(stage)" class="rounded-full bg-guide-well px-2 py-0.5 font-mono text-xs text-guide-ink">{{ countInStage(stage) }}</span>
                        <ChevronDown :size="16" class="shrink-0 text-guide-ink-soft transition-transform"
                                     :class="isOpen(notes.idOf(stageTarget(stage))) ? 'rotate-180' : ''" aria-hidden="true" />
                    </button>

                    <div v-if="isOpen(notes.idOf(stageTarget(stage)))" class="border-t border-guide-rule px-3 pb-3">
                        <!-- The stage's own notes first. -->
                        <div :data-note-target="notes.idOf(stageTarget(stage))" class="pt-3">
                            <p class="m-0 mb-1.5 text-xs font-medium text-guide-ink-soft">{{ $t('arcadia.reader.notes.on_stage') }}</p>
                            <NoteThread :target="stageTarget(stage)" :autofocus="focused === notes.idOf(stageTarget(stage))"
                                        :key="`${notes.idOf(stageTarget(stage))}-${focused === notes.idOf(stageTarget(stage))}`" />
                        </div>

                        <div v-for="item in itemsOf(stage)" :key="item.key" :data-note-target="notes.idOf(itemTarget(item))"
                             class="mt-2 rounded-lg border border-guide-rule">
                            <button type="button" class="flex w-full items-center gap-2 px-2.5 py-2 text-left"
                                    :aria-expanded="isOpen(notes.idOf(itemTarget(item)))" @click="toggle(notes.idOf(itemTarget(item)))">
                                <span class="min-w-0 flex-1 truncate text-sm text-guide-ink">{{ item.title }}</span>
                                <span v-if="notes.countFor(itemTarget(item))" class="font-mono text-xs text-guide-accent">{{ notes.countFor(itemTarget(item)) }}</span>
                                <ChevronDown :size="14" class="shrink-0 text-guide-ink-soft transition-transform"
                                             :class="isOpen(notes.idOf(itemTarget(item))) ? 'rotate-180' : ''" aria-hidden="true" />
                            </button>
                            <div v-if="isOpen(notes.idOf(itemTarget(item)))" class="border-t border-guide-rule p-2.5">
                                <NoteThread :target="itemTarget(item)" :autofocus="focused === notes.idOf(itemTarget(item))"
                                            :key="`${notes.idOf(itemTarget(item))}-${focused === notes.idOf(itemTarget(item))}`" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>
</template>
