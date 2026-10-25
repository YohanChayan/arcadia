<script setup>
/**
 * Arcadia's signed-in surface — the owner's editor — chosen by `shellFor` for any
 * `editor/*` page. The same frame as every dashboard (`DashboardShell`), with its own
 * nav, its brand leading to the editor, and no theme control: Arcadia is
 * dark only. There is one editor per language, at `/es/editor` and `/en/editor`; route names
 * carry it (`es.editor.home`), so the nav is built from the page's language. Above the nav,
 * `EditorLanguageSwitch` says which language's editor this is, in that language's colour, and
 * switches to the same screen in the other.
 *
 * **Slate navy** (2026-10-15, after two greens read as too green): the layout marks `<html>` `data-surface="editor"` while it is
 * mounted, and `app.css` moves the ground and surfaces there. On `<html>`, not on this frame, so
 * dialogs and popovers — teleported to `<body>` — wear it too. The editor is never server-rendered
 * (`disable-ssr`), so nothing paints before the mark is set. Its brand is Arcadia's mark.
 */
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { House, BookOpen, Inbox, MessageSquareHeart, ScrollText, Settings2, Tags } from '@lucide/vue'
import DashboardShell from '@/components/chrome/DashboardShell.vue'
import EditorLanguageSwitch from '@/components/editor/EditorLanguageSwitch.vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'

onMounted(() => { document.documentElement.dataset.surface = 'editor' })
onBeforeUnmount(() => { delete document.documentElement.dataset.surface })

const nav = computed(() => {
    const locale = usePage().props.locale
    const base = `/${locale}/editor`

    return [
        { key: 'home', route: `${locale}.editor.home`, match: base, exact: true, icon: House },
        { key: 'guides', route: `${locale}.editor.guides.index`, match: `${base}/guides`, icon: BookOpen },
        { key: 'prompts', route: `${locale}.editor.prompts.index`, match: `${base}/prompts`, icon: ScrollText },
        { key: 'inquiries', route: `${locale}.editor.inquiries.index`, match: `${base}/requests`, icon: Inbox },
        { key: 'vocabulary', route: `${locale}.editor.vocabulary.index`, match: `${base}/vocabulary`, icon: Tags },
        { key: 'phrases', route: `${locale}.editor.phrases.index`, match: `${base}/phrases`, icon: MessageSquareHeart },
        { key: 'settings', route: `${locale}.editor.settings.edit`, match: `${base}/settings`, icon: Settings2 },
    ]
})
</script>

<template>
    <DashboardShell :nav="nav" nav-label="arcadia.editor.nav.label" item-labels="arcadia.editor.nav.items"
                    :home-href="route(`${$page.props.locale}.editor.home`)">
        <template #brand><ArcadiaMark class="text-xl" /></template>
        <template #sidebar><EditorLanguageSwitch /></template>
        <slot />
    </DashboardShell>
</template>
