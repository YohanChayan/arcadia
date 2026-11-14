<script setup>
/**
 * Site settings → Mail: the emails the site sent, newest first — when, to whom, the subject and
 * which mail (`SentMailLog`). Never their content. Kept 30 days. Site-wide: both editors show the
 * same.
 *
 * Its date filter defaults to the last 24 hours and lives in the query string. A mail that failed
 * to leave never reaches this list: it is a failed job, on the Health tab.
 */
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Mail, RefreshCw } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import SiteSettingsTabs from '@/components/editor/SiteSettingsTabs.vue'
import DateRangeFilter from '@/components/editor/settings/DateRangeFilter.vue'
import { Button } from '@/components/ui/button'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    sent: { type: Object, required: true },
    filter: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const base = 'arcadia.editor.settings.mail'

const when = iso => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })

const current = ref({ ...props.filter })
const loading = ref(false)

const query = () => ({
    mail_range: current.value.range,
    ...(current.value.range === 'custom' ? { mail_from: current.value.from, mail_to: current.value.to } : {}),
})

const reload = () => router.reload({
    data: query(),
    only: ['sent', 'filter'],
    preserveScroll: true,
    onStart: () => { loading.value = true },
    onFinish: () => { loading.value = false },
})

const applyFilter = value => {
    current.value = value
    reload()
}
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.settings.title'), $t('arcadia.editor.settings.tabs.mail')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.settings.title') }]" />

        <PageHeader :title="$t('arcadia.editor.settings.title')" :description="$t('arcadia.editor.settings.description')" />

        <SiteSettingsTabs active="mail" />

        <section class="panel border border-rule p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Eyebrow as="h2" class="flex items-center gap-2">
                        <Mail :size="14" aria-hidden="true" /> {{ $t(`${base}.title`) }}
                    </Eyebrow>
                    <p class="m-0 mt-1 max-w-prose text-xs text-ink-3">{{ $t(`${base}.hint`, { days: sent.retention_days }) }}</p>
                </div>
                <Button type="button" variant="outline" size="sm" :disabled="loading" @click="reload">
                    <RefreshCw :size="14" :class="loading ? 'animate-spin' : ''" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.refresh') }}
                </Button>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <DateRangeFilter :model-value="current" :loading="loading" @update:model-value="applyFilter" />
                <span class="font-mono text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.showing', { shown: sent.mails.length, total: sent.in_range }) }}</span>
            </div>

            <ul v-if="sent.by_kind.length" class="m-0 mb-3 flex list-none flex-wrap gap-2 p-0">
                <li v-for="kind in sent.by_kind" :key="kind.kind" class="rounded-full border border-rule bg-well px-3 py-1 text-xs text-ink-2">
                    {{ kind.kind }} <span class="font-mono text-ink-4">{{ kind.total }}</span>
                </li>
            </ul>

            <p v-if="!sent.mails.length" class="m-0 rounded-lg bg-well px-3 py-2.5 text-sm text-ink-3">{{ $t(`${base}.none`) }}</p>
            <ul v-else class="m-0 grid max-h-[36rem] grid-cols-1 list-none gap-1.5 overflow-y-auto overscroll-contain p-0 pr-1">
                <li v-for="mail in sent.mails" :key="mail.id" class="rounded-lg bg-well px-3 py-2">
                    <p class="m-0 flex flex-wrap items-baseline gap-x-2 font-mono text-[10px] text-ink-4">
                        <span>{{ when(mail.sent_at) }}</span>
                        <span>·</span>
                        <span class="text-ink-2">{{ mail.kind }}</span>
                        <span v-if="mail.locale" class="uppercase">· {{ mail.locale }}</span>
                    </p>
                    <p class="m-0 mt-0.5 break-words text-sm text-ink">{{ mail.subject || '—' }}</p>
                    <p class="m-0 break-all font-mono text-[11px] text-ink-3">
                        <span class="sr-only">{{ $t(`${base}.recipient`) }}: </span>{{ mail.recipient }}
                    </p>
                </li>
            </ul>
            <p v-if="sent.in_range > sent.mails.length" class="m-0 mt-2 text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.capped', { limit: sent.limit }) }}</p>

            <p class="m-0 mt-3 text-[11px] text-ink-4">
                {{ $t(`${base}.failed_hint`) }}
                <Link :href="editorRoute('settings.health')" class="text-ink-2 underline">{{ $t('arcadia.editor.settings.tabs.health') }}</Link>
            </p>
        </section>
    </div>
</template>
