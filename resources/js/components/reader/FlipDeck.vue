<script setup>
/**
 * The "must master" list as a deck of flip cards (`ActivityFamily::FLIP_CARDS`): read the front,
 * think, tap to turn it, and say whether you knew it. A short game, not a test: nothing is ticked
 * and nothing is locked by it.
 *
 * What a reader answered is on their account (`cards.states`, saved at `cards.saveUrl`), so the
 * deck deals only what is due: a card never answered, or one left at "not yet" on an earlier day —
 * by Caracas days, as the streak counts them. A card left at "not yet" today comes back tomorrow; a
 * known one stays known. With nothing due it says how many they know, and offers to go through
 * them all again. The owner and the preview, with nowhere to save, play it for the visit.
 */
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { toast } from 'vue-sonner'
import { Layers, RotateCcw } from '@lucide/vue'

const props = defineProps({
    // [{ key, front, back }]
    cards: { type: Array, required: true },
    // { states: { [key]: { state, at } }, saveUrl } for a reader, else null.
    saved: { type: Object, default: null },
})

const states = ref({ ...(props.saved?.states ?? {}) })

const caracasDay = date => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Caracas' }).format(date)
const today = caracasDay(new Date())

const isDue = card => {
    const answer = states.value[card.key]
    return !answer || (answer.state === 'not_yet' && caracasDay(new Date(answer.at)) < today)
}

const queue = ref(props.cards.filter(isDue).map(card => card.key))
const dealt = ref(queue.value.length)
const flipped = ref(false)
// True while the card turns: its text is hidden until it lands (`.is-turning`).
const turning = ref(false)
let landing = null

const turnTo = side => {
    flipped.value = side
    turning.value = true
    clearTimeout(landing)
    landing = setTimeout(() => { turning.value = false }, 450)
}

const turn = () => turnTo(!flipped.value)

const current = computed(() => props.cards.find(card => card.key === queue.value[0]) ?? null)
const known = computed(() => props.cards.filter(card => states.value[card.key]?.state === 'known').length)
const leftForTomorrow = computed(() => props.cards.some(card => states.value[card.key]?.state === 'not_yet'))

const answer = isKnown => {
    const key = queue.value[0]
    states.value = { ...states.value, [key]: { state: isKnown ? 'known' : 'not_yet', at: new Date().toISOString() } }
    queue.value = queue.value.slice(1)
    // The next card turns back to its front: hidden too, so its answer is never glimpsed.
    turnTo(false)

    if (props.saved?.saveUrl) {
        window.axios.put(props.saved.saveUrl, { key, known: isKnown })
            .catch(() => toast.error(trans('arcadia.reader.cards.failed')))
    }
}

const again = () => {
    queue.value = props.cards.map(card => card.key)
    dealt.value = queue.value.length
    turnTo(false)
}
</script>

<template>
    <div class="mt-3 rounded-2xl border border-guide-rule bg-guide-surface p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3 text-sm">
            <p class="m-0 flex items-center gap-1.5 font-semibold text-guide-accent">
                <Layers :size="16" aria-hidden="true" />
                {{ $t('arcadia.reader.cards.title') }}
            </p>
            <p class="m-0 font-mono text-xs text-guide-ink-soft">{{ $t('arcadia.reader.cards.known', { known, total: cards.length }) }}</p>
        </div>

        <!-- A card: its front until turned, then its back and the two answers. -->
        <template v-if="current">
            <button type="button" class="flip-card mt-4 block w-full text-left" :class="{ 'is-flipped': flipped, 'is-turning': turning }"
                    :aria-pressed="flipped" @click="turn">
                <span class="flip-card-inner">
                    <span class="flip-card-face rounded-xl border-2 border-guide-rule bg-guide-well" :aria-hidden="flipped || undefined">
                        <span class="block font-display text-lg font-semibold text-guide-ink">{{ current.front }}</span>
                        <span class="mt-3 block text-xs text-guide-ink-soft">{{ $t('arcadia.reader.cards.turn') }}</span>
                    </span>
                    <span class="flip-card-face flip-card-back rounded-xl border-2 border-guide-accent bg-guide-well" :aria-hidden="!flipped || undefined">
                        <span class="block text-base text-guide-ink">{{ current.back }}</span>
                    </span>
                </span>
            </button>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <span class="font-mono text-xs text-guide-ink-soft">{{ dealt - queue.length + 1 }} / {{ dealt }}</span>
                <div v-if="flipped" class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full border border-guide-rule px-4 py-2 text-sm font-semibold text-guide-ink hover:border-guide-accent" @click="answer(false)">
                        {{ $t('arcadia.reader.cards.not_yet') }}
                    </button>
                    <button type="button" class="rounded-full bg-guide-accent px-4 py-2 text-sm font-semibold text-guide-on-accent" @click="answer(true)">
                        {{ $t('arcadia.reader.cards.knew') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Nothing due: how they stand, and the deck again. -->
        <div v-else class="mt-4 rounded-xl bg-guide-well p-5 text-center">
            <p class="m-0 font-display text-lg font-semibold text-guide-ink">{{ $t('arcadia.reader.cards.done', { known, total: cards.length }) }}</p>
            <p v-if="leftForTomorrow" class="mt-1 mb-0 text-sm text-guide-ink-soft">{{ $t('arcadia.reader.cards.tomorrow') }}</p>
            <button type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-guide-rule px-4 py-2 text-sm font-semibold text-guide-ink hover:border-guide-accent" @click="again">
                <RotateCcw :size="14" aria-hidden="true" />
                {{ $t('arcadia.reader.cards.again') }}
            </button>
        </div>
    </div>
</template>
