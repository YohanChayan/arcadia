<script setup>
/**
 * The catalogue — `/es/guias` · `/en/guides` (`CatalogueController`, ADR 0023): the guides anyone
 * may ask for, as cards that open each one's presentation. A short header under the aurora, a row
 * of category chips — one line that slides on a phone, wrapping on a wide screen — and the grid:
 * one column on a phone, two, then three.
 *
 * Each card in one of the mark's hues (`--aurora-*`), in turn, as the landing's topics are. A card
 * shows what the presentation shows, shorter: cover, category and level, title, two lines of pitch
 * and the first goals. A guide not there is asked for from the landing's own popup.
 */
import { computed, ref } from 'vue'
import { ArrowLeft, ArrowRight, Check, Languages, Send } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import { Button } from '@/components/ui/button'
import AuroraBackdrop from '@/components/AuroraBackdrop.vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import ArcadiaPhoto from '@/components/ArcadiaPhoto.vue'
import RequestDialog from '@/components/landing/RequestDialog.vue'

const props = defineProps({
    // [{ url, title, level, category (id) | null, goals, pitch, cover: { url, width, height } | null }]
    guides: { type: Array, required: true },
    // [{ id, name }] — only those a listed guide is in.
    categories: { type: Array, required: true },
    homeUrl: { type: String, required: true },
    inquiryUrl: { type: String, required: true },
    requester: { type: Object, default: null },
    contactEmail: { type: String, default: null },
    price: { type: String, default: null },
    priceNote: { type: String, default: null },
    alternates: { type: Object, default: () => ({}) },
    pageTitle: String,
    metaDescription: String,
    noIndex: Boolean,
    ogImage: String,
    ogImageAlt: String,
    siteName: String,
    siteDomain: String,
})

const seo = computed(() => ({
    pageTitle: props.pageTitle,
    metaDescription: props.metaDescription,
    noIndex: props.noIndex,
    ogImage: props.ogImage,
    ogImageAlt: props.ogImageAlt,
    siteName: props.siteName,
    siteDomain: props.siteDomain,
}))

const base = 'arcadia.catalogue'

const alternate = computed(() => Object.values(props.alternates)[0] ?? null)

const hues = ['var(--aurora-3)', 'var(--aurora-1)', 'var(--aurora-5)', 'var(--aurora-2)', 'var(--aurora-6)', 'var(--aurora-4)']

const categoryName = computed(() => Object.fromEntries(props.categories.map(category => [category.id, category.name])))

// The chosen category; null is all of them. Only offered when there is something to choose between.
const chosen = ref(null)
const showChips = computed(() => props.categories.length > 1)

const shown = computed(() => chosen.value === null
    ? props.guides
    : props.guides.filter(guide => guide.category === chosen.value))

// The request popup, for the guide that is not here.
const asking = ref(false)
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="relative isolate">
        <AuroraBackdrop />

        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-5 sm:px-6">
            <a :href="homeUrl" class="no-underline" :aria-label="$t(`${base}.home`)">
                <ArcadiaMark class="text-2xl [--mark-height:1.1em] sm:text-3xl" />
            </a>
            <div class="flex shrink-0 items-center gap-2 text-sm">
                <a v-if="alternate" :href="alternate" class="chip no-underline transition-colors hover:text-ink" :aria-label="$t('arcadia.landing.switch')">
                    <Languages :size="14" aria-hidden="true" />
                    <span class="hidden sm:inline">{{ $t('arcadia.landing.switch') }}</span>
                </a>
                <Button type="button" variant="brand" class="hidden sm:inline-flex" @click="asking = true">
                    {{ $t('arcadia.landing.nav.start') }}
                </Button>
            </div>
        </nav>

        <header class="enter mx-auto max-w-6xl px-4 pt-8 pb-8 sm:px-6 sm:pt-14">
            <a :href="homeUrl" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-3 no-underline hover:text-accent">
                <ArrowLeft :size="14" aria-hidden="true" />
                {{ $t(`${base}.back`) }}
            </a>
            <h1 class="m-0 font-display text-[clamp(2.25rem,7vw,4rem)] font-bold leading-[1.05] tracking-tight text-ink text-balance">
                {{ $t(`${base}.title`) }}
                <span class="text-accent">{{ $t(`${base}.title_accent`) }}</span>
            </h1>
            <p class="mt-4 mb-0 text-lg font-medium text-ink-2 sm:text-xl">
                {{ $t(`${base}.lead`) }}
            </p>
        </header>

        <!-- One line that slides on a phone, past the gutter to the screen's edge; wrapped on a wide one. -->
        <div v-if="showChips" class="mx-auto max-w-6xl sm:px-6">
            <ul role="group" :aria-label="$t(`${base}.filter`)"
                class="chips m-0 flex snap-x gap-2 overflow-x-auto px-4 pb-2 sm:flex-wrap sm:overflow-visible sm:px-0">
                <li class="shrink-0 snap-start list-none">
                    <button type="button" class="chip text-sm transition-colors hover:text-ink" :class="chosen === null && 'chip-on'"
                            :aria-pressed="chosen === null" @click="chosen = null">
                        {{ $t(`${base}.all`) }}
                        <span class="font-mono text-xs opacity-70">{{ guides.length }}</span>
                    </button>
                </li>
                <li v-for="category in categories" :key="category.id" class="shrink-0 snap-start list-none">
                    <button type="button" class="chip text-sm transition-colors hover:text-ink" :class="chosen === category.id && 'chip-on'"
                            :aria-pressed="chosen === category.id" @click="chosen = category.id">
                        {{ category.name }}
                    </button>
                </li>
            </ul>
        </div>

        <main class="mx-auto max-w-6xl px-4 pt-6 pb-24 sm:px-6">
            <ul v-if="shown.length" class="m-0 grid grid-cols-1 gap-5 p-0 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6" aria-live="polite">
                <li v-for="(guide, index) in shown" :key="guide.url" class="list-none">
                    <a :href="guide.url" :style="{ '--hue': hues[index % hues.length] }"
                       class="card panel group flex h-full flex-col overflow-hidden no-underline">
                        <div class="relative aspect-[40/21] overflow-hidden border-b border-rule">
                            <img v-if="guide.cover" :src="guide.cover.url" :width="guide.cover.width" :height="guide.cover.height" alt=""
                                 loading="lazy" class="block size-full object-cover">
                            <ArcadiaPhoto v-else />
                            <span class="hue-wash absolute inset-0" aria-hidden="true"></span>
                        </div>

                        <div class="flex flex-1 flex-col p-5">
                            <p class="m-0 flex flex-wrap gap-x-3 gap-y-1 font-mono text-[0.7rem] uppercase tracking-widest">
                                <span v-if="guide.category && categoryName[guide.category]" class="hue-text">{{ categoryName[guide.category] }}</span>
                                <span class="text-ink-3">{{ guide.level }}</span>
                            </p>
                            <h2 class="mt-2 mb-0 font-display text-xl font-semibold leading-snug text-ink text-balance">{{ guide.title }}</h2>
                            <p v-if="guide.pitch" class="mt-2 mb-0 line-clamp-2 text-sm leading-relaxed text-ink-3">{{ guide.pitch }}</p>

                            <ul v-if="guide.goals.length" class="m-0 mt-4 flex flex-col gap-1.5 p-0">
                                <li v-for="goal in guide.goals.slice(0, 2)" :key="goal" class="flex list-none items-start gap-2 text-sm text-ink-2">
                                    <Check :size="15" :stroke-width="2.5" class="hue-text mt-0.5 shrink-0" aria-hidden="true" />
                                    <span class="line-clamp-1">{{ goal }}</span>
                                </li>
                            </ul>

                            <span class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold text-ink transition-colors group-hover:text-accent">
                                {{ $t(`${base}.open`) }}
                                <ArrowRight :size="16" aria-hidden="true" />
                            </span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- Nothing listed yet: say so, and offer the request instead. -->
            <div v-else class="panel mx-auto flex max-w-xl flex-col items-center px-6 py-14 text-center">
                <p class="m-0 font-display text-xl font-semibold text-ink">{{ $t(`${base}.empty.title`) }}</p>
                <p class="mt-2 mb-0 text-ink-3">{{ $t(`${base}.empty.body`) }}</p>
                <Button type="button" variant="brand" size="lg" class="mt-6" @click="asking = true">
                    <Send :size="18" aria-hidden="true" />
                    {{ $t('arcadia.landing.ask.open') }}
                </Button>
            </div>
        </main>

        <section v-if="guides.length" class="relative isolate overflow-hidden border-t border-rule" aria-labelledby="missing-title">
            <AuroraBackdrop variant="band" />
            <div class="mx-auto flex max-w-3xl flex-col items-center px-4 py-16 text-center sm:px-6 lg:py-20">
                <h2 id="missing-title" class="m-0 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $t(`${base}.missing.title`) }}</h2>
                <p class="mt-2 mb-0 text-ink-3">{{ $t(`${base}.missing.body`) }}</p>
                <Button type="button" variant="brand" size="lg" class="mt-7" @click="asking = true">
                    <Send :size="18" aria-hidden="true" />
                    {{ $t('arcadia.landing.ask.open') }}
                </Button>
            </div>
        </section>

        <RequestDialog v-model:open="asking" :url="inquiryUrl" :requester="requester"
                       :price="price" :price-note="priceNote" :contact-email="contactEmail" />
    </div>
</template>

<style scoped>
/* The phone's slide of chips shows no scrollbar: the cut-off chip at the edge says there is more. */
.chips {
    scrollbar-width: none;
}
.chips::-webkit-scrollbar {
    display: none;
}

.chip-on {
    border-color: var(--accent);
    background-color: color-mix(in oklab, var(--accent) 16%, transparent);
    color: var(--ink);
}

/* A card in its hue: the label, the ticks, a faint wash over the cover and, on hover, the edge —
   as the landing's topic tiles do. */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover,
.card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 0 0 1px color-mix(in oklab, var(--hue) 60%, transparent), 0 18px 40px -18px var(--hue);
}
.hue-text {
    color: var(--hue);
}
.hue-wash {
    background: linear-gradient(to top, color-mix(in oklab, var(--hue) 22%, transparent), transparent 55%);
}

@media (prefers-reduced-motion: no-preference) {
    .enter {
        animation: rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
    }
}
@media (prefers-reduced-motion: reduce) {
    .card:hover,
    .card:focus-visible {
        transform: none;
    }
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(1.5rem);
    }
}
</style>
