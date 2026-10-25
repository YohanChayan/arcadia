<script setup>
/**
 * Arcadia's home — `/es` in Spanish, `/en` in English. Redrawn 2026-10-15 after the rebrand's
 * mock (ADR 0019): a photograph full-bleed behind a navy scrim, the nav over it — the studio, how
 * it works, sign in, the other language, start — then the promise, kept to a title and one line,
 * and its two actions grouped: explore the guides, or ask for one. Asking opens the request popup
 * (`RequestDialog`), which lands in the editor; a topic tile opens it with its topic already
 * written. Exploring opens the catalogue (`Catalogue.vue`).
 *
 * Just below the hero, the guide that proves the promise (`SampleGuide`), drawn by the reader's
 * own components (proof before claim), with the price. Then a dense row of example topics, how a
 * guide is made in open air, what one holds pinned onto a guide card, and the action again on a
 * closing band. Colour below the hero arrives as light: the aurora, in the mark's hues
 * (`--aurora-*`), and each topic carries one of them.
 *
 * The Spanish copy speaks as a Venezuelan would — "chamba", "que no te raspen", "sin que te
 * tumben" — and its examples are the jobs people there are chasing; the English is neutral.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
    ArrowRight, ArrowUpRight, Calculator, ChartNoAxesColumnIncreasing, Check, Coins, Compass, Dumbbell, FileSpreadsheet, Globe,
    Languages, Megaphone, MessagesSquare, Milestone, Palette, PlaySquare, Plus, Send, Smartphone, Sparkles,
    Store,
} from '@lucide/vue'
import { trans } from 'laravel-vue-i18n'
import SeoHead from '@/components/common/SeoHead.vue'
import { Button } from '@/components/ui/button'
import AuroraBackdrop from '@/components/AuroraBackdrop.vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import ArcadiaPhoto from '@/components/ArcadiaPhoto.vue'
import Price from '@/components/landing/Price.vue'
import RequestDialog from '@/components/landing/RequestDialog.vue'
import SampleGuide from '@/components/landing/SampleGuide.vue'

const props = defineProps({
    pageTitle: String,
    metaDescription: String,
    ogImage: String,
    ogImageAlt: String,
    siteName: String,
    siteDomain: String,
    noIndex: Boolean,
    alternates: { type: Object, default: () => ({}) },
    // Where the popup posts a request for a guide.
    inquiryUrl: { type: String, required: true },
    // The guides anyone may ask for, listed — where "explore" goes.
    catalogueUrl: { type: String, required: true },
    // The signed-in account a request is recorded with — `{ name, email }` — or null.
    requester: { type: Object, default: null },
    // The owner's sign-in, behind the small lock in the sample phone.
    loginUrl: { type: String, required: true },
    // What a guide costs, already written: `$3`. Set in the editor's settings.
    // Null when the owner set no price: every place that states it is left out.
    price: { type: String, default: null },
    // A short note shown beside it, set with it: `BCV`. None shows nothing.
    priceNote: { type: String, default: null },
    privacyUrl: { type: String, required: true },
    // Where someone who mistyped their contact in the popup can write. None when unset.
    contactEmail: { type: String, default: null },
})

const seo = computed(() => ({
    pageTitle: props.pageTitle,
    metaDescription: props.metaDescription,
    ogImage: props.ogImage,
    ogImageAlt: props.ogImageAlt,
    siteName: props.siteName,
    siteDomain: props.siteDomain,
    noIndex: props.noIndex,
}))

const alternate = computed(() => Object.values(props.alternates)[0] ?? null)

const base = 'arcadia.landing'

// The studio Arcadia belongs to, linked from the nav and the footer. Its own root picks the language.
const studioUrl = 'https://www.yhndevside.com'

const points = ['free', 'stages', 'phone']

// Each topic in the hue its sample guide has in the phone, so the two read as one; no two
// neighbours match, across or down, in the four-column grid.
const topics = [
    { key: 'excel', icon: FileSpreadsheet, hue: 'var(--aurora-1)' },
    { key: 'instagram', icon: Store, hue: 'var(--aurora-2)' },
    { key: 'english', icon: Globe, hue: 'var(--aurora-3)' },
    { key: 'accounts', icon: Calculator, hue: 'var(--aurora-6)' },
    { key: 'usdt', icon: Coins, hue: 'var(--aurora-4)' },
    { key: 'social', icon: Megaphone, hue: 'var(--aurora-1)' },
    { key: 'design', icon: Palette, hue: 'var(--aurora-5)' },
]

const how = [
    { key: 'ask', icon: MessagesSquare },
    { key: 'curate', icon: Sparkles },
    { key: 'follow', icon: Smartphone },
]

// In the order of the pins on the guide card beside them.
const inside = [
    { key: 'stages', icon: Milestone },
    { key: 'videos', icon: PlaySquare },
    { key: 'practice', icon: Dumbbell },
    { key: 'progress', icon: ChartNoAxesColumnIncreasing },
]

const steps = ['learn', 'practice', 'advance', 'achieve']

// The popup, and the topic it opens with.
const asking = ref(false)
const askingFor = ref('')

const ask = (topic = '') => {
    askingFor.value = topic
    asking.value = true
}

// The hero's quick picks: a few of the topics below, by key, each opening the popup with it.
const quick = ['excel', 'english', 'design', 'social']

// Whether the hero's action has scrolled up out of view — the phone's bottom bar shows then.
const heroAction = ref(null)
const pastAction = ref(false)
let watcher = null

onMounted(() => {
    watcher = new IntersectionObserver(([entry]) => {
        pastAction.value = !entry.isIntersecting && entry.boundingClientRect.top < 0
    })
    watcher.observe(heroAction.value)
})
onBeforeUnmount(() => watcher?.disconnect())

const ideas = computed(() => topics.map(topic => trans(`${base}.topics.items.${topic.key}.name`)))
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="relative isolate">
        <!-- The hero, as the mock draws it: the photograph full-bleed, a navy scrim on the text's
             side, the nav over it, and the promise with its one action and a row of three. -->
        <header class="relative isolate overflow-hidden">
            <!-- Faded out by a mask, not painted over with a ground colour: `body` paints the ground
                 as a gradient, so no single colour matches it and a fade to one left a visible line. -->
            <div aria-hidden="true" class="absolute inset-0 -z-10 [mask-image:linear-gradient(to_bottom,black_60%,transparent)]">
                <ArcadiaPhoto />
                <div class="hero-scrim absolute inset-0"></div>
            </div>

            <nav class="relative mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-5 sm:px-6">
                <ArcadiaMark class="text-2xl [--mark-height:1.1em] sm:text-3xl" />

                <div class="flex shrink-0 items-center gap-1 text-sm sm:gap-2">
                    <a :href="studioUrl" class="group hidden items-center gap-1 rounded-control px-3 py-2 text-ink-3 no-underline transition-colors hover:text-ink lg:inline-flex" :title="`${$t(`${base}.by`)} YHN Dev Side`">
                        YHN Dev Side
                        <ArrowUpRight :size="13" class="text-ink-4 transition-colors group-hover:text-accent" aria-hidden="true" />
                    </a>
                    <span class="hidden h-4 w-px bg-rule lg:block" aria-hidden="true"></span>
                    <a href="#how" class="hidden rounded-control px-3 py-2 text-ink-2 no-underline transition-colors hover:text-ink md:block">{{ $t(`${base}.nav.how`) }}</a>
                    <a :href="loginUrl" class="rounded-control px-3 py-2 text-ink-2 no-underline transition-colors hover:text-ink">{{ $t(`${base}.nav.sign_in`) }}</a>
                    <a v-if="alternate" :href="alternate" class="chip no-underline transition-colors hover:text-ink" :aria-label="$t(`${base}.switch`)">
                        <Languages :size="14" aria-hidden="true" />
                        <span class="hidden sm:inline">{{ $t(`${base}.switch`) }}</span>
                    </a>
                    <Button type="button" variant="brand" class="ml-1 hidden sm:inline-flex" @click="ask()">
                        {{ $t(`${base}.nav.start`) }}
                    </Button>
                </div>
            </nav>

            <!-- The promise on the left; the two actions together, grouped, on the right of a wide
                 screen — over the photograph, centred in the hero's height. On a phone the group sits
                 in the flow, under the promise. One element either way, so the bottom bar's watcher
                 (`heroAction`) sees the same buttons at every width. -->
            <div class="enter mx-auto grid max-w-6xl gap-x-10 px-4 pt-14 pb-28 sm:px-6 lg:grid-cols-[minmax(0,42rem)_1fr] lg:pt-24 lg:pb-40">
                <div class="lg:col-start-1">
                    <h1 class="m-0 font-display text-[clamp(2.5rem,9vw,4.75rem)] font-bold leading-[1.05] tracking-tight text-ink text-balance">
                        {{ $t(`${base}.hero.title`) }}
                        <!-- `title_lead` may be empty: Spanish sets its whole second line in the accent. -->
                        <span class="block"><template v-if="$t(`${base}.hero.title_lead`)">{{ $t(`${base}.hero.title_lead`) }} </template><span class="text-accent">{{ $t(`${base}.hero.title_accent`) }}</span></span>
                    </h1>

                    <p class="mt-6 mb-0 max-w-xl text-lg font-medium text-ink-2 sm:text-2xl">
                        {{ $t(`${base}.hero.lead`) }}
                    </p>
                </div>

                <!-- Explore first and filled, ask second and tinted: one primary, never two. -->
                <!-- On a phone, two compact buttons side by side at their own width, the price under
                     them; on a wide screen the stacked, full-size pair over the photograph. -->
                <div ref="heroAction" class="mt-8 flex flex-wrap items-center gap-2.5 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0 lg:w-80 lg:flex-col lg:items-stretch lg:gap-3 lg:self-center lg:justify-self-center">
                    <Button as="a" :href="catalogueUrl" variant="brand" size="cta" class="no-underline lg:h-14 lg:w-full lg:gap-3 lg:text-lg">
                        <Compass :size="18" aria-hidden="true" />
                        {{ $t(`${base}.hero.explore`) }}
                    </Button>
                    <Button type="button" variant="soft" size="cta" class="over-photo lg:h-14 lg:w-full lg:gap-3 lg:text-lg" @click="ask()">
                        {{ $t(`${base}.hero.cta`) }}
                        <ArrowRight :size="18" aria-hidden="true" />
                    </Button>
                    <!-- Two lines, never a "·" left at the start of the second; shadowed to read over the photo. -->
                    <p class="over-photo-text m-0 basis-full text-sm text-ink-2 lg:mt-1 lg:text-center">
                        <span v-if="price" class="flex items-baseline gap-1.5 lg:justify-center">
                            {{ $t(`${base}.price.only`) }}
                            <Price :amount="price" :note="priceNote" class="text-lg" />
                        </span>
                        <span class="block">{{ $t(`${base}.hero.price_note`) }}</span>
                    </p>
                </div>

                <!-- One tap from an idea to the request, its topic already written. -->
                <div class="mt-9 lg:col-start-1 lg:mt-10">
                    <p class="m-0 text-xs font-medium uppercase tracking-widest text-ink-3">{{ $t(`${base}.hero.quick.title`) }}</p>
                    <ul class="mt-3 flex flex-wrap gap-2 p-0">
                        <li v-for="key in quick" :key="key" class="list-none">
                            <button type="button" class="chip text-sm transition-colors hover:border-accent hover:text-ink"
                                    @click="ask($t(`${base}.topics.items.${key}.name`))">
                                {{ $t(`${base}.hero.quick.items.${key}`) }}
                            </button>
                        </li>
                        <li class="list-none">
                            <button type="button" class="chip text-sm transition-colors hover:border-accent hover:text-ink" @click="ask()">
                                <Plus :size="14" aria-hidden="true" />
                                {{ $t(`${base}.hero.quick.other`) }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- The guide that proves the promise, just below it: tick a step. Then what one costs. -->
        <section id="try" class="reveal relative isolate mx-auto grid max-w-6xl scroll-mt-6 grid-cols-1 items-start gap-12 px-4 pb-24 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:gap-10 lg:pb-32" aria-labelledby="try-title">

            <!-- At the top, beside the phone's top, and held there while the phone scrolls by: centred
                 against a phone that tall, it left an empty column above it. -->
            <div class="min-w-0 text-center lg:sticky lg:top-24 lg:pt-16 lg:text-left">
                <h2 id="try-title" class="m-0 font-display text-2xl font-semibold text-ink text-balance sm:text-3xl">{{ $t(`${base}.hero.try`) }}</h2>
                <ul class="mt-6 flex flex-wrap justify-center gap-2 p-0 lg:justify-start">
                    <li v-for="point in points" :key="point" class="chip list-none text-sm">
                        <Check :size="14" :stroke-width="3" class="text-accent" aria-hidden="true" />
                        {{ $t(`${base}.hero.points.${point}`) }}
                    </li>
                </ul>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-5 lg:justify-start">
                    <Button type="button" variant="brand" size="lg" @click="ask()">
                        <Send :size="18" aria-hidden="true" />
                        {{ $t('arcadia.landing.ask.open') }}
                    </Button>
                    <p v-if="price" class="m-0 flex items-baseline gap-1.5 text-ink-3">
                        <span class="text-sm">{{ $t('arcadia.landing.price.only') }}</span>
                        <Price :amount="price" :note="priceNote" class="text-3xl" />
                        <span class="text-sm">{{ $t('arcadia.landing.price.per') }}</span>
                    </p>
                </div>
            </div>

            <!-- `minmax(0, …)` columns and `min-w-0` items: a grid column otherwise grows to its widest
                 content, and the goal chips are one unwrapped row meant to scroll sideways — on a phone
                 it stretched the whole section past the screen. -->
            <div class="min-w-0">
                <SampleGuide :login-url="loginUrl" />
            </div>
        </section>

        <!-- Dense: named topics, tight together. -->
        <section class="reveal mx-auto max-w-6xl px-4 sm:px-6" aria-labelledby="topics-title">
            <div>
                <h2 id="topics-title" class="m-0 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $t('arcadia.landing.topics.title') }}</h2>
                <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.landing.topics.lead') }}</p>
            </div>

            <ul class="mt-6 grid grid-cols-1 gap-3 p-0 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="topic in topics" :key="topic.key" class="list-none">
                    <button type="button" :style="{ '--hue': topic.hue }"
                            class="tile panel flex h-full w-full items-start gap-3 p-4 text-left"
                            @click="ask($t(`${base}.topics.items.${topic.key}.name`))">
                        <span class="tile-icon flex size-11 shrink-0 items-center justify-center rounded-xl">
                            <component :is="topic.icon" :size="22" aria-hidden="true" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display text-[0.95rem] font-semibold leading-snug text-ink">{{ $t(`${base}.topics.items.${topic.key}.name`) }}</span>
                            <span class="mt-0.5 block text-xs leading-snug text-ink-3">{{ $t(`${base}.topics.items.${topic.key}.hint`) }}</span>
                        </span>
                    </button>
                </li>
                <li class="list-none">
                    <button type="button" class="flex h-full w-full items-start gap-3 rounded-panel border border-dashed border-edge p-4 text-left transition-colors hover:border-accent"
                            @click="ask()">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-dashed border-edge text-ink-3">
                            <Plus :size="22" aria-hidden="true" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display text-[0.95rem] font-semibold leading-snug text-ink">{{ $t('arcadia.landing.topics.other.name') }}</span>
                            <span class="mt-0.5 block text-xs leading-snug text-ink-3">{{ $t('arcadia.landing.topics.other.hint') }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <!-- Open: three steps on one line, with air around them. -->
        <section id="how" class="reveal mx-auto max-w-6xl scroll-mt-6 px-4 py-24 sm:px-6 lg:py-32" aria-labelledby="how-title">
            <h2 id="how-title" class="m-0 text-center font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $t('arcadia.landing.how.title') }}</h2>

            <ol class="relative mt-14 grid grid-cols-1 gap-12 p-0 sm:grid-cols-3 sm:gap-8">
                <!-- The line the three steps sit on. -->
                <span aria-hidden="true" class="absolute top-7 right-[16.5%] left-[16.5%] hidden h-px bg-linear-to-r from-transparent via-accent/60 to-transparent sm:block"></span>

                <li v-for="(step, index) in how" :key="step.key" class="relative flex list-none flex-col items-center text-center">
                    <span class="flex size-14 items-center justify-center rounded-full bg-raised-solid text-accent ring-1 ring-accent/40 shadow-[var(--glow)]">
                        <component :is="step.icon" :size="24" aria-hidden="true" />
                    </span>
                    <span class="mt-4 font-mono text-xs tracking-widest text-ink-4">0{{ index + 1 }}</span>
                    <h3 class="mt-1 mb-0 font-display text-lg font-semibold text-ink">{{ $t(`${base}.how.steps.${step.key}.title`) }}</h3>
                    <p class="mt-2 mb-0 max-w-xs text-sm leading-relaxed text-ink-3">{{ $t(`${base}.how.steps.${step.key}.body`) }}</p>
                </li>
            </ol>
        </section>

        <!-- What a guide holds, pinned onto one: each numbered pin is the line beside it. -->
        <section class="reveal mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 pb-24 sm:px-6 md:grid-cols-2 lg:gap-20 lg:pb-32" aria-labelledby="inside-title">
            <div aria-hidden="true" class="panel relative order-last mx-3 p-5 sm:p-6 md:order-first">
                <div class="relative">
                    <span class="pin top-0">1</span>
                    <p class="m-0 font-mono text-xs font-medium uppercase tracking-widest text-guide-accent">{{ $t('arcadia.reader.stage', { number: 1 }) }}</p>
                    <p class="mt-1 mb-0 font-display text-lg font-semibold text-guide-ink">{{ $t('arcadia.landing.sample.samples.excel.stage.title') }}</p>
                    <p class="mt-1 mb-0 text-sm text-guide-ink-soft">
                        <span class="font-medium text-guide-ink">{{ $t('arcadia.reader.when_done') }}</span> {{ $t('arcadia.landing.sample.samples.excel.stage.goal') }}
                    </p>
                </div>

                <div class="relative mt-5 flex items-center gap-3 rounded-xl bg-guide-well p-3">
                    <span class="pin">2</span>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-guide-accent text-guide-on-accent">
                        <PlaySquare :size="18" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-guide-ink">{{ $t('arcadia.landing.sample.samples.excel.items.b.title') }}</span>
                        <span class="block text-xs text-guide-ink-soft">{{ $t('arcadia.landing.sample.samples.excel.items.b.hint') }}</span>
                    </span>
                </div>

                <div class="relative mt-3 rounded-xl border-2 border-guide-accent p-3">
                    <span class="pin">3</span>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-guide-accent">{{ $t('arcadia.reader.challenge') }}</span>
                    <span class="mt-0.5 block text-sm font-medium text-guide-ink">{{ $t('arcadia.landing.sample.samples.excel.challenge.title') }}</span>
                </div>

                <div class="relative mt-5 flex items-center gap-3">
                    <span class="pin">4</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-guide-well">
                        <div class="h-full w-3/5 rounded-full bg-guide-accent"></div>
                    </div>
                    <span class="font-mono text-xs text-guide-ink-soft">{{ $t('arcadia.reader.progress', { done: 3, total: 5 }) }}</span>
                </div>
            </div>

            <div>
                <h2 id="inside-title" class="m-0 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $t('arcadia.landing.inside.title') }}</h2>
                <ol class="mt-8 space-y-6 p-0">
                    <li v-for="(feature, index) in inside" :key="feature.key" class="flex list-none gap-4">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent font-mono text-sm font-semibold text-on-accent">{{ index + 1 }}</span>
                        <div>
                            <h3 class="m-0 flex items-center gap-2 font-display text-base font-semibold text-ink">
                                <component :is="feature.icon" :size="18" class="text-accent" aria-hidden="true" />
                                {{ $t(`${base}.inside.items.${feature.key}.title`) }}
                            </h3>
                            <p class="mt-1 mb-0 text-sm leading-relaxed text-ink-3">{{ $t(`${base}.inside.items.${feature.key}.body`) }}</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <!-- The motto, and the one action again, on a band across the page. -->
        <section class="band reveal relative isolate overflow-hidden border-y border-rule" aria-labelledby="request-title">
            <AuroraBackdrop variant="band" />
            <div class="mx-auto flex max-w-4xl flex-col items-center px-4 py-20 text-center sm:px-6 lg:py-24">
                <p class="m-0 font-display text-3xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
                    {{ $t('arcadia.landing.motto.line_a') }}
                    <span class="text-accent">=</span>
                    <span class="block bg-linear-to-r from-(--aurora-3) via-(--aurora-1) to-(--aurora-2) bg-clip-text text-transparent">{{ $t('arcadia.landing.motto.line_b') }}</span>
                </p>

                <ol class="mt-8 flex flex-wrap items-center justify-center gap-x-2 gap-y-3 p-0">
                    <li v-for="(step, index) in steps" :key="step" class="flex list-none items-center gap-2">
                        <span class="chip text-sm text-ink-2">{{ $t(`${base}.motto.steps.${step}`) }}</span>
                        <ArrowRight v-if="index < steps.length - 1" :size="14" class="text-accent" aria-hidden="true" />
                    </li>
                </ol>

                <h2 id="request-title" class="mt-14 mb-0 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $t('arcadia.landing.request.title') }}</h2>
                <p class="mt-2 mb-0 text-ink-3">{{ $t('arcadia.landing.request.body') }}</p>
                <Button type="button" variant="brand" size="lg" class="mt-7" @click="ask()">
                    <Send :size="18" aria-hidden="true" />
                    {{ $t('arcadia.landing.ask.open') }}
                </Button>
                <p v-if="price" class="mt-3 mb-0 flex items-baseline gap-1.5 text-ink-3">
                    <span class="text-sm">{{ $t('arcadia.landing.price.only') }}</span>
                    <Price :amount="price" :note="priceNote" class="text-2xl" />
                    <span class="text-sm">{{ $t('arcadia.landing.price.per') }}</span>
                </p>
            </div>
        </section>

        <!-- On a phone the hero's action scrolls away fast: once it has, the action and its price
             stay one tap away at the bottom of the screen. Never over the open popup. -->
        <Transition enter-active-class="transition-transform duration-300 ease-out motion-reduce:transition-none"
                    enter-from-class="translate-y-full"
                    leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
                    leave-to-class="translate-y-full">
            <div v-if="pastAction && !asking"
                 class="fixed inset-x-0 bottom-0 z-(--layer-chrome) border-t border-rule bg-ground/90 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl sm:hidden">
                <div class="flex items-center justify-between gap-3">
                    <p v-if="price" class="m-0 flex items-baseline gap-1.5 text-ink-3">
                        <span class="text-xs">{{ $t(`${base}.price.only`) }}</span>
                        <Price :amount="price" :note="priceNote" class="text-xl" />
                    </p>
                    <Button type="button" variant="brand" @click="ask()">
                        {{ $t(`${base}.hero.cta`) }}
                        <ArrowRight :size="16" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </Transition>

        <RequestDialog v-model:open="asking" :url="inquiryUrl" :requester="requester"
                       :topic="askingFor" :suggestions="ideas" :price="price" :price-note="priceNote" :contact-email="contactEmail" />

        <footer class="pb-14 pt-12 text-center text-sm text-ink-4 sm:pb-44">
            {{ $t('arcadia.landing.by') }}
            <a :href="studioUrl" class="font-medium text-ink-3 no-underline hover:text-accent">YHN Dev Side</a>
            <span class="mx-2" aria-hidden="true">·</span>
            <Link :href="privacyUrl" class="text-ink-3 no-underline hover:text-accent">{{ $t('arcadia.landing.privacy') }}</Link>
        </footer>
    </div>
</template>

<style scoped>
/* The hero's tinted button sits on the photograph on a wide screen, where the sun can be right
   behind it: a frosted navy under its tint keeps it legible whatever is behind. */
.over-photo {
    background-color: color-mix(in oklab, var(--ground-a) 62%, transparent);
    backdrop-filter: blur(10px);
}
.over-photo:hover {
    background-color: color-mix(in oklab, var(--ground-a) 45%, color-mix(in oklab, var(--accent) 20%, transparent));
}
.over-photo-text {
    text-shadow: 0 1px 2px var(--ground-a), 0 0 14px var(--ground-a);
}

/* The closing band: a deep ground, the lights at its ends. */
.band {
    background-color: color-mix(in oklab, var(--ground-b) 70%, transparent);
}

/* A topic in its own hue: the icon's well and, on hover, the edge. */
.tile {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 0 1px color-mix(in oklab, var(--hue) 60%, transparent), 0 14px 32px -16px var(--hue);
}
.tile-icon {
    color: var(--hue);
    background-color: color-mix(in oklab, var(--hue) 18%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--hue) 35%, transparent);
}

/* A numbered pin on the guide card, matching the list beside it. */
.pin {
    position: absolute;
    left: -2.1rem;
    display: flex;
    width: 1.5rem;
    height: 1.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background-color: var(--accent);
    color: var(--on-accent);
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 0 0 4px var(--ground-a);
}

/* Motion is decoration only: everything is in place without it, and none of it runs under
   reduced motion. The hero rises once on load; each later section rises as it scrolls into
   view, where the browser can tie an animation to scrolling — elsewhere it simply is there. */
@media (prefers-reduced-motion: no-preference) {
    .enter {
        animation: rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
    }
    .enter-late {
        animation-delay: 0.15s;
    }

    @supports (animation-timeline: view()) {
        .reveal {
            animation: rise linear both;
            animation-timeline: view();
            animation-range: entry 0% entry 35%;
        }
    }
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(1.5rem);
    }
}
</style>
