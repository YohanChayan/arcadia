<script setup>
/**
 * The editor's frame: the sidebar and the content column. The layout passes only its
 * navigation; everything else is here.
 */
import { useFlashMessage } from '@/composables/useFlashMessage'
import ShellSidebar from '@/components/chrome/ShellSidebar.vue'

defineProps({
    nav: { type: Array, required: true },
    navLabel: { type: String, required: true },
    itemLabels: { type: String, required: true },
    // Passed through to `ShellSidebar` — see there.
    homeHref: { type: String, default: '/' },
})

useFlashMessage()
</script>

<template>
    <div class="flex min-h-screen bg-ground">
        <ShellSidebar :nav="nav" :nav-label="navLabel" :item-labels="itemLabels" :home-href="homeHref">
            <template v-if="$slots.brand" #brand><slot name="brand" /></template>
            <template v-if="$slots.sidebar" #context><slot name="sidebar" /></template>
        </ShellSidebar>

        <div class="relative flex-1 min-w-0">
            <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0" mode="out-in"
                appear>
                <main class="min-h-screen px-4 lg:px-8" :key="$page.url">
                    <slot />
                </main>
            </transition>
        </div>
    </div>
</template>
