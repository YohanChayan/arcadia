<script setup>
/**
 * Arcadia's privacy note. Short, because the product keeps little: the reader's account and what
 * hangs off it — their guides, colours, ticks and requests — three technical cookies, and
 * YouTube only once the reader presses play.
 */
import { ArrowLeft } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'

const props = defineProps({
    pageTitle: String,
    noIndex: Boolean,
    email: { type: String, default: null },
})

const sections = ['account', 'progress', 'devices', 'cookies', 'youtube', 'links', 'visits', 'requests', 'contact']
</script>

<template>
    <SeoHead :page-title="props.pageTitle" :no-index="props.noIndex" />

    <article class="relative mx-auto max-w-2xl px-4 pb-20 pt-10 sm:px-6 sm:pt-16">
        <Link :href="`/${$page.props.locale}`" class="inline-flex items-center gap-1.5 text-sm text-ink-3 no-underline hover:text-accent">
            <ArrowLeft :size="14" aria-hidden="true" />
            {{ $t('arcadia.privacy.back') }}
        </Link>

        <h1 class="mt-6 mb-0 font-display text-3xl font-bold text-ink">{{ $t('arcadia.privacy.title') }}</h1>
        <p class="mt-1 mb-0 font-mono text-xs text-ink-4">{{ $t('arcadia.privacy.updated') }}</p>
        <p class="mt-6 mb-0 text-lg text-ink-2">{{ $t('arcadia.privacy.lead') }}</p>

        <section v-for="key in sections" v-show="key !== 'contact' || email" :key="key" class="mt-8">
            <h2 class="m-0 font-display text-lg font-semibold text-ink">{{ $t(`arcadia.privacy.sections.${key}.title`) }}</h2>
            <p class="mt-2 mb-0 leading-relaxed text-ink-2">{{ $t(`arcadia.privacy.sections.${key}.body`, { email: email ?? '' }) }}</p>
            <a v-if="key === 'youtube'" href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer"
               class="mt-2 inline-block text-sm text-accent no-underline hover:underline">{{ $t('arcadia.privacy.sections.youtube.link') }}</a>
        </section>
    </article>
</template>
