<script setup>
/**
 * The landing page's proof: a guide the visitor can tick, inside a phone, drawn by the reader's
 * own components (proof before claim) — so it can never look different from the guides people
 * receive. Six samples, each led by a goal a visitor recognises as theirs ("Excel enough to
 * pass the interview"), because a goal is what makes a guide worth starting.
 *
 * Nothing here leaves the page. Its lines are the guide's own kinds (`note`, `practice`), so no
 * YouTube player is offered and nothing loads from YouTube, and its ticks live in memory only —
 * they must not land in the storage a real guide's progress uses.
 *
 * The phone moves to the next sample every five seconds, as a carousel would. It holds still
 * while the pointer or the focus is on it, and stops for good once the visitor picks a sample or
 * ticks a line: from then on the phone is theirs. Never under reduced motion.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Calculator, ChevronsRight, Coins, FileSpreadsheet, Languages, LockOpen, Palette, PartyPopper, Store, Target } from '@lucide/vue'
import ProgressBar from '@/components/reader/ProgressBar.vue'
import ReaderStage from '@/components/reader/ReaderStage.vue'

defineProps({
    // The owner's sign-in, behind the lock in the status bar.
    loginUrl: { type: String, required: true },
})

const base = 'arcadia.landing.sample.samples'

const samples = [
    { key: 'excel', icon: FileSpreadsheet, hue: 'var(--aurora-1)' },
    { key: 'instagram', icon: Store, hue: 'var(--aurora-2)' },
    { key: 'english', icon: Languages, hue: 'var(--aurora-3)' },
    { key: 'usdt', icon: Coins, hue: 'var(--aurora-4)' },
    { key: 'canva', icon: Palette, hue: 'var(--aurora-5)' },
    { key: 'accounts', icon: Calculator, hue: 'var(--aurora-6)' },
]

const LINES = ['a', 'b', 'c', 'd']

const current = ref('excel')

// The carousel. Stopped once the visitor takes the phone over, and held while they are on it.
const ROTATE_EVERY = 5000
let timer = null
let stopped = false

const picker = ref(null)
const figure = ref(null)

const pause = () => {
    clearInterval(timer)
    timer = null
}

const resume = () => {
    if (stopped || timer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return
    }

    timer = setInterval(advance, ROTATE_EVERY)
}

const stop = () => {
    stopped = true
    pause()
}

// On a phone the goals are one row that scrolls sideways; the one now shown is brought into it.
const revealCurrentTab = async () => {
    await nextTick()

    const row = picker.value
    const tab = row?.querySelector('[aria-pressed="true"]')

    if (tab && row.scrollWidth > row.clientWidth) {
        row.scrollTo({ left: tab.offsetLeft - row.offsetLeft - 16, behavior: 'smooth' })
    }
}

const advance = () => {
    if (document.hidden) {
        return
    }

    const at = samples.findIndex(sample => sample.key === current.value)
    current.value = samples[(at + 1) % samples.length].key
    revealCurrentTab()
}

const pick = key => {
    stop()
    current.value = key
}

// Focus moving between the phone's own controls is still focus on the phone.
const onFocusOut = event => {
    if (!figure.value?.contains(event.relatedTarget)) {
        resume()
    }
}

onMounted(resume)
onBeforeUnmount(pause)

// On a phone the goals scroll sideways; a hint says so until the row has been scrolled once.
const hasScrolledGoals = ref(false)
const onGoalsScroll = event => {
    if (event.target.scrollLeft > 8) {
        hasScrolledGoals.value = true
    }
}

const sample = computed(() => samples.find(sample => sample.key === current.value))

const stage = computed(() => {
    const at = `${base}.${current.value}`

    return {
        title: trans(`${at}.stage.title`),
        goal: trans(`${at}.stage.goal`),
        cards: [
            {
                key: 'videos',
                kind: 'source',
                byline: trans(`${at}.card.byline`),
                description: trans(`${at}.card.description`),
                items: LINES.map(line => ({
                    key: `${current.value}.${line}`,
                    kind: 'note',
                    is_available: true,
                    title: trans(`${at}.items.${line}.title`),
                    hint: trans(`${at}.items.${line}.hint`),
                })),
            },
            {
                key: 'challenge',
                kind: 'challenge',
                description: trans(`${at}.challenge.description`),
                items: [{
                    key: `${current.value}.challenge`,
                    kind: 'practice',
                    is_available: true,
                    title: trans(`${at}.challenge.title`),
                    hint: trans(`${at}.challenge.hint`),
                }],
            },
        ],
    }
})

const keys = computed(() => stage.value.cards.flatMap(card => card.items.map(item => item.key)))

// Every sample starts with two lines ticked, so the bar shows what it is for before anyone
// touches it. Keys carry the sample's name, so switching back keeps what was ticked there.
const done = ref(new Set(samples.flatMap(({ key }) => [`${key}.a`, `${key}.b`])))

const progress = {
    isDone: key => done.value.has(key),
    toggle: key => {
        stop()
        const next = new Set(done.value)
        next.has(key) ? next.delete(key) : next.add(key)
        done.value = next
    },
    count: list => list.filter(key => done.value.has(key)).length,
}

const complete = computed(() => progress.count(keys.value) === keys.value.length)
</script>

<template>
    <figure ref="figure" class="relative m-0 mx-auto w-full min-w-0 max-w-[25rem] lg:flex lg:max-w-none lg:items-center lg:gap-4" :aria-label="$t('arcadia.landing.sample.label')" :style="{ '--hue': sample.hue }"
            @mouseenter="pause" @mouseleave="resume" @focusin="pause" @focusout="onFocusOut">
        <!-- Which goal the phone shows, each guide by its own title. On a wide screen, a stack to
             the phone's left, one under another, the chosen one nudged towards the phone. On a
             phone there is no room beside it, so one row above it that scrolls sideways, faded
             at its ends to say there is more. -->
        <div ref="picker" class="picker -mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:mb-0 lg:w-44 lg:shrink-0 lg:snap-none lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
             role="group" :aria-label="$t('arcadia.landing.sample.pick')" @scroll.passive="onGoalsScroll">
            <button v-for="option in samples" :key="option.key" type="button" :aria-pressed="current === option.key"
                    class="inline-flex shrink-0 snap-start items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-all duration-200 lg:items-start lg:rounded-2xl lg:py-2 lg:text-left lg:leading-snug lg:whitespace-normal"
                    :class="current === option.key ? 'tab-on border-transparent text-ink lg:translate-x-2' : 'border-rule bg-raised text-ink-3 hover:text-ink'"
                    :style="{ '--hue': option.hue }"
                    @click="pick(option.key)">
                <component :is="option.icon" :size="15" class="shrink-0 lg:mt-0.5" aria-hidden="true" />
                {{ $t(`${base}.${option.key}.guide`) }}
            </button>
        </div>

        <!-- Phone only: there is more to the right. Kept in its space once it fades, so the phone
             below does not jump. -->
        <p aria-hidden="true" class="mt-2 mb-4 flex items-center justify-end gap-1 text-xs text-ink-4 transition-opacity duration-300 lg:hidden"
           :class="hasScrolledGoals ? 'opacity-0' : 'opacity-100'">
            {{ $t('arcadia.landing.sample.swipe') }}
            <ChevronsRight :size="14" class="nudge text-accent" />
        </p>

        <div class="relative mx-auto w-full max-w-[25rem] lg:min-w-0 lg:flex-1">
            <!-- The light the phone sits in takes the chosen goal's hue. -->
            <div aria-hidden="true" class="halo pointer-events-none absolute inset-x-0 top-12 bottom-10 -z-10"></div>

            <!-- The phone: a bezel, the island, and the guide inside. -->
            <div class="rounded-[2.75rem] border border-rule bg-raised-solid p-2.5 shadow-[var(--glow)]">
                <div class="relative overflow-hidden rounded-[2.25rem] bg-ground">
                    <div class="relative flex items-center justify-between px-6 pt-3 pb-1 font-mono text-[0.68rem] font-medium text-ink-3">
                        <span>9:41</span>
                        <span aria-hidden="true" class="absolute top-2.5 left-1/2 h-5 w-24 -translate-x-1/2 rounded-full bg-raised-solid"></span>
                        <!-- The owner's way in, dressed as one of the phone's own status icons: there for
                             whoever looks, never in a visitor's way. -->
                        <a :href="loginUrl" class="-m-1.5 p-1.5 text-ink-4 no-underline transition-colors hover:text-ink-2"
                           :aria-label="$t('arcadia.landing.sample.sign_in')" :title="$t('arcadia.landing.sample.sign_in')">
                            <LockOpen :size="11" aria-hidden="true" />
                        </a>
                    </div>

                    <div class="sample-scroll max-h-[36rem] overflow-y-auto px-3 pt-2 pb-4">
                        <Transition name="swap" mode="out-in">
                            <div :key="current">
                                <header class="px-2 pt-2 pb-4">
                                    <p class="m-0 font-display text-xl font-bold text-guide-ink">{{ $t(`${base}.${current}.guide`) }}</p>
                                    <p class="mt-0.5 mb-0 text-xs text-guide-ink-soft">{{ $t(`${base}.${current}.meta`) }}</p>

                                    <!-- The goal leads: it is why anyone starts. -->
                                    <div class="goal mt-4 rounded-2xl p-3.5">
                                        <p class="m-0 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-guide-ink">
                                            <Target :size="14" aria-hidden="true" />
                                            {{ $t('arcadia.landing.sample.goal') }}
                                        </p>
                                        <p class="mt-1 mb-0 text-sm font-medium leading-snug text-guide-ink">{{ $t(`${base}.${current}.goal`) }}</p>
                                    </div>

                                    <div class="mt-4">
                                        <ProgressBar :done="progress.count(keys)" :total="keys.length" />
                                    </div>

                                    <Transition name="pop">
                                        <p v-if="complete" class="mt-3 mb-0 flex items-center gap-2 rounded-xl bg-guide-well px-3 py-2 text-sm font-semibold text-guide-done">
                                            <PartyPopper :size="16" aria-hidden="true" />
                                            {{ $t('arcadia.landing.sample.complete') }}
                                        </p>
                                    </Transition>
                                </header>

                                <ReaderStage :stage="stage" :number="1" :progress="progress" />
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- A <p>, not a <figcaption>: the caption sits in the phone's column, and a
                 figcaption must be the figure's own child. -->
            <p class="mt-3 mb-0 text-center text-xs text-ink-4">{{ $t('arcadia.landing.sample.note') }}</p>
        </div>
    </figure>
</template>

<style scoped>
/* The row of goals scrolls without a bar, and fades out at both ends to say there is more. */
.picker {
    scrollbar-width: none;
    mask-image: linear-gradient(to right, transparent, black 1rem, black calc(100% - 1rem), transparent);
}
.picker::-webkit-scrollbar {
    display: none;
}
/* Stacked beside the phone, nothing is cut off, so nothing fades. */
@media (min-width: 1024px) {
    .picker {
        mask-image: none;
    }
}

/* The chosen goal's tab, lit in its own hue. */
.tab-on {
    background-color: color-mix(in oklab, var(--hue) 28%, transparent);
    box-shadow: 0 0 0 1px color-mix(in oklab, var(--hue) 70%, transparent), 0 8px 24px -10px var(--hue);
}

.halo {
    background: radial-gradient(closest-side, color-mix(in oklab, var(--hue) 55%, transparent), transparent);
    filter: blur(48px);
    transition: background 0.5s ease;
}

.goal {
    background: linear-gradient(135deg, color-mix(in oklab, var(--hue) 30%, transparent), color-mix(in oklab, var(--hue) 8%, transparent));
    box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--hue) 45%, transparent);
}

/* The phone scrolls inside itself; its own bar would be a desktop window's, not a phone's. */
.sample-scroll {
    scrollbar-width: none;
}
.sample-scroll::-webkit-scrollbar {
    display: none;
}

@media (prefers-reduced-motion: no-preference) {
    .nudge {
        animation: nudge 1.4s ease-in-out infinite;
    }

    .swap-enter-active,
    .swap-leave-active {
        transition: opacity 0.18s ease, transform 0.18s ease;
    }
    .swap-enter-from {
        opacity: 0;
        transform: translateX(0.75rem);
    }
    .swap-leave-to {
        opacity: 0;
        transform: translateX(-0.75rem);
    }

    .pop-enter-active {
        transition: opacity 0.25s ease, transform 0.35s cubic-bezier(0.3, 1.6, 0.5, 1);
    }
    .pop-enter-from {
        opacity: 0;
        transform: scale(0.9);
    }
}

@keyframes nudge {
    50% {
        transform: translateX(3px);
    }
}
</style>
