<script setup>
/**
 * The tab bar across the top of every Site settings screen. Each tab is its own route, not a
 * panel of one page: a tab loads only its own data, so the price form never waits on the log
 * scan, and each tab can be linked to. It looks like the vocabulary screen's tabs, but its
 * triggers are links, and the open tab is the one whose route is current.
 *
 * Only the host settings are per language; the monitoring tabs that join here are site-wide.
 */
import { Link } from '@inertiajs/vue3'
import { Bot, FileWarning, HeartPulse, Mail, Store } from '@lucide/vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

defineProps({
    active: { type: String, required: true },
})

const editorRoute = useEditorRoute()

const tabs = [
    { key: 'host', route: 'settings.edit', icon: Store },
    { key: 'health', route: 'settings.health', icon: HeartPulse },
    { key: 'logs', route: 'settings.logs', icon: FileWarning },
    { key: 'agents', route: 'settings.agents', icon: Bot },
    { key: 'mail', route: 'settings.mail', icon: Mail },
]
</script>

<template>
    <nav :aria-label="$t('arcadia.editor.settings.tabs.label')"
         class="mb-5 flex w-full gap-1 overflow-x-auto border-b border-rule">
        <Link v-for="tab in tabs" :key="tab.key" :href="editorRoute(tab.route)"
              :aria-current="tab.key === active ? 'page' : undefined"
              class="-mb-px inline-flex flex-none items-center gap-2 border-b-2 border-transparent px-4 py-2.5 text-sm font-medium text-ink-3 no-underline hover:text-ink aria-[current=page]:border-accent aria-[current=page]:text-ink">
            <component :is="tab.icon" :size="16" aria-hidden="true" />
            {{ $t(`arcadia.editor.settings.tabs.${tab.key}`) }}
        </Link>
    </nav>
</template>
