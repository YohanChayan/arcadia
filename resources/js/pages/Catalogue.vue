<script setup>
/**
 * The catalogue — `/es/guias` · `/en/guides` (`CatalogueController`, ADR 0023): the guides anyone
 * may ask for, dense enough that most of the catalogue reads in a screen or two. A short header
 * under the aurora, with the price stated once — every guide costs the same, so no tile repeats
 * it, and with no price set there is nothing to state.
 *
 * Below it, one section per category — guides with none in a last "Others" — each a dense grid of
 * small tiles: the cover cut 3:2 and the title. Every guide of a section shows; there
 * is no "see all". The chips above stick to the top and jump to a section, the one in view lit.
 * With a single section there is nothing to jump between: no chips and no heading.
 *
 * Each section in one of the mark's hues (`--aurora-*`), by its category's place, as the landing's
 * topics are. A guide not there is asked for from the landing's own popup.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { ArrowLeft, Languages, Send, Sparkles } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import { Button } from '@/components/ui/button'
import AuroraBackdrop from '@/components/AuroraBackdrop.vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import ArcadiaPhoto from '@/components/ArcadiaPhoto.vue'
import Price from '@/components/landing/Price.vue'
import RequestDialog from '@/components/landing/RequestDialog.vue'

const props = defineProps({
    // [{ url, title, category (id) | null, goals, pitch, cover: { url, width, height } | null }]
    guides: { type: Array, required: true },
    // [{ id, name }] — only those a listed guide is in.
    categories: { type: Array, required: true },
    homeUrl: { type: String, required: true },
    inquiryUrl: { type: String, required: true },
    requester: { type: Object, default: null },
    contactEmail: { type: String, default: null },
    // Already written (`$2`), or null when none is set.
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

// The section a guide is in: its category, or "others" for one with none.
const OTHERS = 'others'
const sectionOf = guide => (guide.category !== null && categoryName.value[guide.category] ? guide.category : OTHERS)

// [{ id, anchor, name, hue, guides }] — the categories in their order, then the guides with none.
// The hue follows the category's place, so a category keeps its colour whichever others show.
const sections = computed(() => {
    const bySection = new Map(props.categories.map((category, index) => [category.id, {
        id: category.id,
        name: category.name,
        hue: hues[index % hues.length],
        guides: [],
    }]))
    bySection.set(OTHERS, { id: OTHERS, name: trans(`${base}.others`), hue: hues[props.categories.length % hues.length], guides: [] })
    props.guides.forEach(guide => bySection.get(sectionOf(guide)).guides.push(guide))

    return [...bySection.values()]
        .filter(section => section.guides.length)
        .map(section => ({ ...section, anchor: `cat-${section.id}` }))
})

const showSections = computed(() => sections.value.length > 1)

// The section in view, lit on its chip: the topmost one crossing the band under the sticky chips.
const current = ref(null)
let observer = null

onMounted(async () => {
    if (!showSections.value || typeof IntersectionObserver === 'undefined') {
        return
    }
    await nextTick()
    const visible = new Set()
    observer = new IntersectionObserver(entries => {
        entries.forEach(entry => (entry.isIntersecting ? visible.add(entry.target.id) : visible.delete(entry.target.id)))
        current.value = sections.value.find(section => visible.has(section.anchor))?.anchor ?? current.value
    }, { rootMargin: '-80px 0px -55% 0px' })
    sections.value.forEach(section => {
        const element = document.getElementById(section.anchor)
        if (element) {
            observer.observe(element)
        }
    })
})

onBeforeUnmount(() => observer?.disconnect())

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

        <header class="enter mx-auto flex max-w-6xl flex-col gap-4 px-4 pt-4 pb-6 sm:flex-row sm:items-end sm:justify-between sm:px-6 sm:pt-8">
            <div>
                <a :href="homeUrl" class="mb-3 inline-flex items-center gap-1.5 text-sm text-ink-3 no-underline hover:text-accent">
                    <ArrowLeft :size="14" aria-hidden="true" />
                    {{ $t(`${base}.back`) }}
                </a>
                <h1 class="m-0 font-display text-[clamp(1.75rem,5vw,2.75rem)] font-bold leading-[1.1] tracking-tight text-ink text-balance">
                    {{ $t(`${base}.title`) }}
                    <span class="text-accent">{{ $t(`${base}.title_accent`) }}</span>
                </h1>
                <p class="mt-2 mb-0 font-medium text-ink-2 sm:text-lg">
                    {{ $t(`${base}.lead`) }}
                </p>
            </div>

            <!-- The price, once: every guide costs the same. -->
            <p v-if="price && guides.length" class="price m-0 inline-flex shrink-0 items-center gap-2.5 self-start rounded-full px-4 py-2 sm:self-auto">
                <Sparkles :size="16" class="text-accent" aria-hidden="true" />
                <span class="text-sm font-medium text-ink-2">{{ $t(`${base}.price_lead`) }}</span>
                <Price :amount="price" :note="priceNote" class="text-2xl leading-none" />
            </p>
        </header>

        <!-- Sticky: one line that slides on a phone, past the gutter to the screen's edge; wrapped on a wide one. -->
        <nav v-if="showSections" :aria-label="$t(`${base}.sections`)" class="bar sticky top-0 z-20">
            <ul class="chips m-0 mx-auto flex max-w-6xl snap-x gap-2 overflow-x-auto px-4 py-2.5 sm:flex-wrap sm:overflow-visible sm:px-6">
                <li v-for="section in sections" :key="section.id" class="shrink-0 snap-start list-none">
                    <a :href="`#${section.anchor}`" :style="{ '--hue': section.hue }"
                       class="chip text-sm no-underline transition-colors hover:text-ink" :class="current === section.anchor && 'chip-on'"
                       :aria-current="current === section.anchor ? 'true' : undefined">
                        <span class="dot" aria-hidden="true"></span>
                        {{ section.name }}
                        <span class="font-mono text-xs opacity-70">{{ section.guides.length }}</span>
                    </a>
                </li>
            </ul>
        </nav>

        <main class="mx-auto max-w-6xl px-4 pt-4 pb-20 sm:px-6">
            <div v-if="guides.length" class="flex flex-col gap-10">
                <section v-for="section in sections" :id="section.anchor" :key="section.id"
                         :style="{ '--hue': section.hue }" class="scroll-mt-16"
                         :aria-labelledby="showSections ? `${section.anchor}-title` : undefined">
                    <header v-if="showSections" class="mb-3 flex items-center gap-2.5">
                        <span class="dot" aria-hidden="true"></span>
                        <h2 :id="`${section.anchor}-title`" class="m-0 font-display text-base font-semibold text-ink sm:text-lg">{{ section.name }}</h2>
                        <span class="hue-text font-mono text-xs">{{ section.guides.length }}</span>
                        <span class="rule ml-1 h-px flex-1" aria-hidden="true"></span>
                    </header>

                    <ul class="m-0 grid grid-cols-2 gap-x-3 gap-y-5 p-0 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5">
                        <li v-for="guide in section.guides" :key="guide.url" class="list-none">
                            <a :href="guide.url" :title="guide.title" class="tile group block no-underline">
                                <div class="relative aspect-[3/2] overflow-hidden rounded-lg border border-rule">
                                    <img v-if="guide.cover" :src="guide.cover.url" :width="guide.cover.width" :height="guide.cover.height" alt=""
                                         loading="lazy" class="block size-full object-cover transition-transform duration-300 group-hover:scale-105">
                                    <ArcadiaPhoto v-else />
                                    <span class="hue-wash absolute inset-0" aria-hidden="true"></span>
                                </div>
                                <p class="m-0 mt-2 line-clamp-3 text-sm font-semibold leading-snug text-ink-2 transition-colors group-hover:text-ink">
                                    {{ guide.title }}
                                </p>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

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
/* The price, lit in the accent: the one figure on the page. */
.price {
    border: 1px solid color-mix(in oklab, var(--accent) 45%, transparent);
    background-color: color-mix(in oklab, var(--accent) 12%, transparent);
    box-shadow: 0 10px 30px -14px var(--accent);
}

/* The sticky chips sit on a frosted strip of the ground, so tiles pass under them unread. */
.bar {
    background-color: color-mix(in oklab, var(--ground-a) 78%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--rule);
}

/* The phone's slide of chips shows no scrollbar: the cut-off chip at the edge says there is more. */
.chips {
    scrollbar-width: none;
}
.chips::-webkit-scrollbar {
    display: none;
}

.chip-on {
    border-color: var(--hue);
    background-color: color-mix(in oklab, var(--hue) 18%, transparent);
    color: var(--ink);
}

.dot {
    display: inline-block;
    width: 0.5rem;
    height: 0.5rem;
    flex-shrink: 0;
    border-radius: 9999px;
    background-color: var(--hue);
}
.rule {
    background: linear-gradient(to right, color-mix(in oklab, var(--hue) 40%, transparent), transparent);
}

.tile:focus-visible {
    outline: 2px solid var(--hue);
    outline-offset: 3px;
    border-radius: 0.5rem;
}
.tile:hover .rounded-lg {
    box-shadow: 0 12px 28px -16px var(--hue);
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
    .tile img {
        transform: none !important;
    }
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(1.5rem);
    }
}
</style>
