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
         class="mb-5 inline-flex max-w-full flex-wrap gap-1 rounded-lg bg-well p-1">
        <Link v-for="tab in tabs" :key="tab.key" :href="editorRoute(tab.route)"
              :aria-current="tab.key === active ? 'page' : undefined"
              class="inline-flex flex-none items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium text-ink-3 no-underline transition-[color,box-shadow] hover:text-ink aria-[current=page]:bg-raised-solid aria-[current=page]:text-ink aria-[current=page]:shadow-[var(--shadow-sm)]">
            <component :is="tab.icon" :size="16" aria-hidden="true" />
            {{ $t(`arcadia.editor.settings.tabs.${tab.key}`) }}
        </Link>
    </nav>
</template>
