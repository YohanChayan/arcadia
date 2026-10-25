<script setup>
/**
 * The browser tab's name for an editor screen: what it is about, then the screen, then Arcadia —
 * "Lisbon in three days · Content · Arcadia". The most specific part leads, because a tab
 * truncates from the right and several editor tabs open side by side differ only there.
 *
 * Built on the client from the same keys the screen's `PageHeader` reads, so a heading and its
 * tab cannot drift. The editor is never server-rendered and never indexed, so none of the SEO
 * tags `SeoHead` carries apply here — only the title and `noindex`.
 */
import { computed } from 'vue'

const props = defineProps({
    // Most specific first. An empty part — a guide saved without a title — is skipped.
    parts: { type: Array, required: true },
})

const text = computed(() => [...props.parts, 'Arcadia'].filter(Boolean).join(' · '))
</script>

<template>
    <Head>
        <title>{{ text }}</title>
        <meta head-key="robots" name="robots" content="noindex, nofollow">
    </Head>
</template>
