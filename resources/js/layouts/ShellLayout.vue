<script setup>
/**
 * Every Arcadia page's outer frame: the shell `shellFor` names for it, the canonical link the
 * page's props carry, and the toaster. Assigned centrally by `app.js`, never by the page.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import 'vue-sonner/style.css'
import { Toaster } from '@/components/ui/sonner'
import PublicLayout from './PublicLayout.vue'
import EditorLayout from './EditorLayout.vue'
import ReaderLayout from './ReaderLayout.vue'
import { shellFor } from '@/lib/shell-for.js'

const SHELLS = {
    public: PublicLayout,
    editor: EditorLayout,
    reader: ReaderLayout,
}

const page = usePage()
const shell = computed(() => SHELLS[shellFor(page.component, page.props.user)])
const canonicalUrl = computed(() => page.props.canonicalUrl)
</script>

<template>
    <Head>
        <link v-if="canonicalUrl" head-key="canonical" rel="canonical" :href="canonicalUrl">
    </Head>

    <div id="app" class="p-0 m-0">
        <component :is="shell">
            <slot />
        </component>

        <!-- Arcadia is dark only (ADR 0019). -->
        <Toaster :richColors="true" theme="dark" :toastOptions="{ style: { zIndex: 9999 } }" />
    </div>
</template>
