<script setup>
/**
 * A reader's own guides, on their phone first: one card per guide, painted in that reader's
 * colours, with its link once it can be read, where they stand on it — lines, videos, links and
 * practices done, stages finished, last tick — and the colours to change. The figures are there
 * to make plain that progress is what they tick: a guide with nothing ticked says so. Changing
 * the colours repaints the card as they pick; it is saved only on Save.
 *
 * Short motivation phrases (`MotivationPicker`) — one welcoming them under the title, one on each
 * card for where they stand on that guide — to be read in a glance and left behind.
 *
 * A reader removed from every guide lands here too, on the empty state — their account is kept.
 */
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { BookOpen, Dumbbell, Flag, Link2, Palette, PlayCircle } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ReaderColoursSheet from '@/components/ReaderColoursSheet.vue'
import { appearanceTokens } from '@/lib/appearance.js'

const props = defineProps({
    email: { type: String, required: true },
    // [{ reader, title, level, palette, accent, url, progress }] — url and progress are null until
    // it can be read; progress is { done, total, videos, links, practices, stages: { done, total }, last_ticked_at }.
    guides: { type: Array, required: true },
    // A short phrase welcoming them, or null.
    welcome: { type: String, default: null },
    appearanceOptions: { type: Object, required: true },
    pageTitle: String,
    noIndex: Boolean,
})

const seo = computed(() => ({ pageTitle: props.pageTitle, noIndex: props.noIndex }))

// The card being recoloured, and what is picked for it so far.
const pickingId = ref(null)
const picking = computed(() => props.guides.find(guide => guide.reader === pickingId.value) ?? null)
const palette = ref('arcadia')
const accent = ref('teal')

const pick = guide => {
    palette.value = guide.palette
    accent.value = guide.accent
    pickingId.value = guide.reader
}

const tokensFor = guide => guide.reader === pickingId.value
    ? appearanceTokens(props.appearanceOptions, palette.value, accent.value)
    : appearanceTokens(props.appearanceOptions, guide.palette, guide.accent)

const page = usePage()
const saveUrl = guide => route(`${page.props.locale}.reader.appearance`, { reader: guide.reader })

const percent = ({ done, total }) => (total ? Math.round((done / total) * 100) : 0)

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })

// The figures under the bar: only those this guide has lines of.
const tallies = progress => [
    { key: 'videos', icon: PlayCircle, ...progress.videos },
    { key: 'links', icon: Link2, ...progress.links },
    { key: 'practices', icon: Dumbbell, ...progress.practices },
    { key: 'stages', icon: Flag, ...progress.stages },
].filter(tally => tally.total > 0)
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="mx-auto max-w-2xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12">
        <!-- Signing out is in the reader's bar (`ReaderLayout`), on every reader page. -->
        <header class="min-w-0">
            <h1 class="m-0 font-display text-2xl font-bold text-ink sm:text-3xl">{{ $t('arcadia.my_guides.title') }}</h1>
            <p v-if="welcome" class="mt-2 mb-0 text-base font-medium text-accent">{{ welcome }}</p>
            <p class="mt-1 mb-0 truncate text-sm text-ink-3">{{ email }}</p>
        </header>

        <div v-if="guides.length" class="mt-8 flex flex-col gap-4">
            <!-- Each card in its reader's colours: the same tokens the guide page reads. -->
            <article v-for="guide in guides" :key="guide.reader" :style="tokensFor(guide)"
                     class="overflow-hidden rounded-2xl border border-guide-rule bg-guide-surface">
                <div class="bg-guide-ground p-5 sm:p-6">
                    <!-- The cover's square beside the title, once the owner has uploaded one. -->
                    <div class="flex items-start gap-4">
                        <img v-if="guide.cover" :src="guide.cover" alt="" width="160" height="160" loading="lazy"
                             class="size-16 shrink-0 rounded-xl border border-guide-rule object-cover sm:size-20">
                        <div class="min-w-0">
                            <p class="m-0 font-mono text-xs uppercase tracking-widest text-guide-accent">{{ guide.level }}</p>
                            <h2 class="mt-1.5 mb-0 font-display text-xl font-semibold leading-snug text-guide-ink">{{ guide.title }}</h2>
                        </div>
                    </div>
                    <p v-if="!guide.url" class="mt-2 mb-0 text-sm text-guide-ink-soft">{{ $t('arcadia.my_guides.preparing') }}</p>

                    <p v-if="guide.phrase" class="mt-3 mb-0 text-sm font-semibold text-guide-accent">{{ guide.phrase }}</p>

                    <div v-if="guide.progress" class="mt-4">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-semibold text-guide-ink">{{ $t('arcadia.my_guides.progress.done', { done: guide.progress.done, total: guide.progress.total }) }}</span>
                            <span class="font-mono text-guide-accent">{{ percent(guide.progress) }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-guide-well" role="progressbar"
                             :aria-valuenow="percent(guide.progress)" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-guide-accent transition-[width]" :style="{ width: `${percent(guide.progress)}%` }"></div>
                        </div>

                        <ul class="m-0 mt-3 flex flex-wrap gap-x-4 gap-y-1.5 p-0 text-xs text-guide-ink-soft">
                            <li v-for="tally in tallies(guide.progress)" :key="tally.key" class="flex list-none items-center gap-1.5">
                                <component :is="tally.icon" :size="14" class="text-guide-accent" aria-hidden="true" />
                                {{ $t(`arcadia.my_guides.progress.${tally.key}`, { done: tally.done, total: tally.total }) }}
                            </li>
                        </ul>

                        <!-- Progress is only what they tick: say so where nothing is ticked yet. -->
                        <p class="mt-3 mb-0 text-xs text-guide-ink-soft">
                            {{ guide.progress.last_ticked_at
                                ? $t('arcadia.my_guides.progress.last', { date: formatDate(guide.progress.last_ticked_at) })
                                : $t('arcadia.my_guides.progress.none') }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-guide-rule p-4">
                    <a v-if="guide.url" :href="guide.url"
                       class="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-guide-accent px-4 py-2.5 text-sm font-semibold text-guide-on-accent no-underline">
                        <BookOpen :size="16" aria-hidden="true" />
                        {{ $t('arcadia.my_guides.open') }}
                    </a>
                    <button type="button" @click="pick(guide)"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-guide-rule px-4 py-2.5 text-sm font-medium text-guide-ink">
                        <Palette :size="16" aria-hidden="true" />
                        {{ $t('arcadia.my_guides.colours.action') }}
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="panel mt-8 p-6 text-center">
            <p class="m-0 font-semibold text-ink">{{ $t('arcadia.my_guides.empty.title') }}</p>
            <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.my_guides.empty.hint') }}</p>
        </div>

        <ReaderColoursSheet v-if="picking" v-model:palette="palette" v-model:accent="accent"
                            :open="picking !== null" :title="picking.title" :options="appearanceOptions"
                            :save-url="saveUrl(picking)" :saved="picking"
                            @close="pickingId = null" />
    </div>
</template>
