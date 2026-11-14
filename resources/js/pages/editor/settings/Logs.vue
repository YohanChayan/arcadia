<script setup>
/**
 * Site settings → Logs: the errors this environment wrote to `laravel.log`, newest first, the first
 * line of each. Site-wide: both editors show the same.
 *
 * Ported from yhndevside's error-log card. Its date filter defaults to the last 24 hours and lives
 * in the query string. A failed assistant turn records no reason of its own: it is found here, at
 * the turn's time.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { CircleCheck, CircleAlert, CircleX, RefreshCw } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import SiteSettingsTabs from '@/components/editor/SiteSettingsTabs.vue'
import DateRangeFilter from '@/components/editor/settings/DateRangeFilter.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    log: { type: Object, required: true },
    filter: { type: Object, required: true },
})

const LEVEL = {
    ok: { icon: CircleCheck, text: 'text-success', ring: 'border-rule' },
    warn: { icon: CircleAlert, text: 'text-signal', ring: 'border-signal/40' },
    error: { icon: CircleX, text: 'text-danger', ring: 'border-danger/40' },
}

const size = bytes => {
    if (!bytes) return '—'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
    return `${(bytes / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`
}

const current = ref({ ...props.filter })
const loading = ref(false)

const query = () => ({
    log_range: current.value.range,
    ...(current.value.range === 'custom' ? { log_from: current.value.from, log_to: current.value.to } : {}),
})

const reload = () => router.reload({
    data: query(),
    only: ['log', 'filter'],
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
    <EditorTitle :parts="[$t('arcadia.editor.settings.title'), $t('arcadia.editor.settings.tabs.logs')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.settings.title') }]" />

        <PageHeader :title="$t('arcadia.editor.settings.title')" :description="$t('arcadia.editor.settings.description')" />

        <SiteSettingsTabs active="logs" />

        <section class="panel border p-5" :class="LEVEL[log.level].ring">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Eyebrow as="h2" class="flex flex-wrap items-center gap-2">
                        <component :is="LEVEL[log.level].icon" :size="14" :class="LEVEL[log.level].text" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.logs.title') }}
                        <span v-if="log.exists" class="font-mono normal-case tracking-normal text-ink-4">{{ $t('arcadia.editor.settings.logs.size', { size: size(log.bytes) }) }} · {{ $t('arcadia.editor.settings.logs.environment', { env: log.environment }) }}</span>
                        <span v-if="log.last_day" class="font-mono normal-case tracking-normal text-signal">· {{ $t('arcadia.editor.settings.logs.last_day', { count: log.last_day }) }}</span>
                    </Eyebrow>
                    <p class="m-0 mt-1 max-w-prose text-xs text-ink-3">{{ $t('arcadia.editor.settings.logs.hint') }}</p>
                </div>
                <Button type="button" variant="outline" size="sm" :disabled="loading" @click="reload">
                    <RefreshCw :size="14" :class="loading ? 'animate-spin' : ''" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.refresh') }}
                </Button>
            </div>

            <p v-if="!log.exists" class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.settings.logs.missing') }}</p>
            <template v-else>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <DateRangeFilter :model-value="current" :loading="loading" @update:model-value="applyFilter" />
                    <span class="font-mono text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.showing', { shown: log.errors.length, total: log.in_range }) }}</span>
                </div>

                <p v-if="!log.errors.length" class="m-0 rounded-lg bg-well px-3 py-2.5 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.filter.empty') }}</p>
                <ul v-else class="m-0 grid max-h-[36rem] grid-cols-1 list-none gap-1.5 overflow-y-auto overscroll-contain p-0 pr-1">
                    <li v-for="(error, i) in log.errors" :key="`${error.at}-${i}`" class="rounded-lg bg-well px-3 py-2">
                        <p class="m-0 font-mono text-[10px] text-ink-4">{{ error.at }} · <span class="uppercase text-danger">{{ error.level }}</span></p>
                        <p class="m-0 mt-0.5 break-words font-mono text-[11px] text-ink-2">{{ error.message }}</p>
                    </li>
                </ul>
                <p v-if="log.in_range > log.errors.length" class="m-0 mt-2 text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.capped', { limit: log.limit }) }}</p>
            </template>
        </section>
    </div>
</template>
