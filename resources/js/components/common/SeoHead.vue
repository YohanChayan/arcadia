<script setup>
/**
 * The SEO prop contract, rendered once.
 *
 * `PageSeo::metadata()` returns these seven props and every public page declared
 * the same ~17 meta tags to consume them. That block was copied into eleven components,
 * which meant a change to the Twitter card shape was eleven edits and any page that missed
 * one disagreed with the rest silently — nothing renders differently, the tag is just wrong.
 *
 * The share image's absolute URL is built from the site's own domain (`ArcadiaSite::preview()`),
 * never the request host — a mirror must not preview itself.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    pageTitle: { type: String, default: '' },
    metaDescription: { type: String, default: '' },
    metaKeywords: { type: String, default: '' },
    ogImage: { type: String, default: '' },
    ogImageAlt: { type: String, default: '' },
    hreflangUrls: { type: Object, default: () => ({}) },
    noIndex: { type: Boolean, default: false },
    // Whose page a link preview names, and the domain its image is served from.
    siteName: { type: String, default: 'Arcadia' },
    siteDomain: { type: String, default: 'arcadia.yhndevside.com' },
})

const imageUrl = computed(() => `https://${props.siteDomain}${props.ogImage}`)

// `canonicalUrl` is shared globally, so pages do not plumb it through. The canonical <link>
// itself belongs to ShellLayout and is deliberately not emitted here — two of them would be
// worse than none.
const canonicalUrl = computed(() => usePage().props.canonicalUrl)
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta head-key="description" name="description" :content="metaDescription">
        <meta head-key="keywords" name="keywords" :content="metaKeywords">
        <meta head-key="robots" name="robots" :content="noIndex ? 'noindex, nofollow' : 'index, follow'">

        <link v-if="hreflangUrls?.en" head-key="hreflang-en" rel="alternate" hreflang="en" :href="hreflangUrls.en">
        <link v-if="hreflangUrls?.es" head-key="hreflang-es" rel="alternate" hreflang="es" :href="hreflangUrls.es">
        <link v-if="hreflangUrls?.['x-default']" head-key="hreflang-x-default" rel="alternate" hreflang="x-default" :href="hreflangUrls['x-default']">

        <meta head-key="og:site_name" property="og:site_name" :content="siteName" />
        <meta head-key="og:title" property="og:title" :content="pageTitle" />
        <meta head-key="og:description" property="og:description" :content="metaDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta v-if="canonicalUrl" head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta head-key="og:image" property="og:image" :content="imageUrl" />
        <meta head-key="og:image:alt" property="og:image:alt" :content="ogImageAlt" />

        <meta head-key="twitter:domain" name="twitter:domain" :content="siteDomain" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="pageTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="metaDescription" />
        <meta v-if="canonicalUrl" head-key="twitter:url" name="twitter:url" :content="canonicalUrl" />
        <meta head-key="twitter:image" name="twitter:image" :content="imageUrl" />
        <meta head-key="twitter:image:alt" name="twitter:image:alt" :content="ogImageAlt" />
    </Head>
</template>
