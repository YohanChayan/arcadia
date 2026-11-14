<script setup>
/**
 * Site settings → Health: whether the machinery behind the site is running — who is on the site
 * right now, the queue and its worker, failed jobs, mail, the environment. Site-wide: both editors
 * show the same.
 *
 * Ported from yhndevside's Health tab. Every card carries a level computed by `SiteHealth`, so the
 * colour on screen comes from the same judgement as the server's.
 *
 * Failed jobs have a date filter, defaulting to the last 24 hours. It lives in the query string,
 * so a Retry or Delete — which redirects back — keeps it. A retry asks first and names the job:
 * retrying a guide's email sends that email again.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { CircleCheck, CircleAlert, CircleX, RefreshCw, RotateCcw, Trash2, Send, Users, Eraser } from '@lucide/vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import SiteSettingsTabs from '@/components/editor/SiteSettingsTabs.vue'
import DateRangeFilter from '@/components/editor/settings/DateRangeFilter.vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    health: { type: Object, required: true },
    visitors: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const LEVEL = {
    ok: { icon: CircleCheck, text: 'text-success', ring: 'border-rule' },
    warn: { icon: CircleAlert, text: 'text-signal', ring: 'border-signal/40' },
    error: { icon: CircleX, text: 'text-danger', ring: 'border-danger/40' },
}

const refreshing = ref(false)
const refresh = () => router.reload({
    only: ['health', 'visitors'],
    onStart: () => { refreshing.value = true },
    onFinish: () => { refreshing.value = false },
})

const age = seconds => {
    if (seconds === null || seconds === undefined) return '—'
    if (seconds < 60) return `${seconds}s`
    if (seconds < 3600) return `${Math.round(seconds / 60)} min`
    return `${Math.round(seconds / 3600)} h`
}
const when = iso => iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const size = bytes => {
    if (!bytes) return '—'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
    return `${(bytes / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`
}

// ── actions ──
const busy = ref(null)
const act = (key, method, url, onDone = () => {}) => router.visit(url, {
    method,
    preserveScroll: true,
    only: ['health', 'message'],
    onStart: () => { busy.value = key },
    onFinish: () => { busy.value = null; onDone() },
})

const retrying = ref(null) // the job awaiting confirmation: { uuid, job }
const retry = () => act('retry', 'post', editorRoute('settings.health.retry', retrying.value.uuid), () => { retrying.value = null })

const forgetting = ref(null)
const forget = () => act('forget', 'delete', editorRoute('settings.health.forget', forgetting.value), () => { forgetting.value = null })

const testMail = () => act('mail', 'post', editorRoute('settings.health.test-mail'))

const flushing = ref(false)
// Flushes what the list is showing: the `failed_*` filter travels with the request.
const flush = () => router.delete(editorRoute('settings.health.flush'), {
    data: query(),
    preserveScroll: true,
    only: ['health', 'message'],
    onStart: () => { busy.value = 'flush' },
    onSuccess: () => { flushing.value = false },
    onFinish: () => { busy.value = null },
})

// ── date filter ──
const filters = ref({ ...props.health.filters })
const filtering = ref(false)

const query = () => Object.fromEntries(Object.entries(filters.value).flatMap(([name, filter]) => [
    [`${name}_range`, filter.range],
    ...(filter.range === 'custom' ? [[`${name}_from`, filter.from], [`${name}_to`, filter.to]] : []),
]))

// "the last 7 days", "2026-09-01 – 2026-09-10" — names the period in the flush button and dialog.
const scope = () => {
    const filter = filters.value.failed
    return trans(`arcadia.editor.settings.health.failed.scopes.${filter.range}`, { from: filter.from ?? '', to: filter.to ?? '' })
}

const applyFilter = value => {
    filters.value = { ...filters.value, failed: value }
    router.reload({
        data: query(),
        only: ['health'],
        preserveScroll: true,
        onStart: () => { filtering.value = true },
        onFinish: () => { filtering.value = false },
    })
}
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.settings.title'), $t('arcadia.editor.settings.tabs.health')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.settings.title') }]" />

        <PageHeader :title="$t('arcadia.editor.settings.title')" :description="$t('arcadia.editor.settings.description')" />

        <SiteSettingsTabs active="health" />

        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <p class="m-0 max-w-prose text-xs text-ink-3">{{ $t('arcadia.editor.settings.health.hint') }}</p>
                <Button type="button" variant="outline" size="sm" :disabled="refreshing" @click="refresh">
                    <RefreshCw :size="14" :class="refreshing ? 'animate-spin' : ''" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.refresh') }}
                </Button>
            </div>

            <!-- ── right now ── -->
            <section class="panel p-5">
                <Eyebrow as="h2" class="flex items-center gap-2">
                    <Users :size="14" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.visitors.title') }}
                </Eyebrow>
                <p class="m-0 mt-1 mb-4 text-xs text-ink-3">{{ $t('arcadia.editor.settings.health.visitors.hint') }}</p>

                <div v-if="visitors.measurable" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg border border-accent/30 bg-accent/8 px-4 py-3">
                        <p class="m-0 flex items-center gap-2 font-mono text-3xl font-semibold tabular-nums text-ink">
                            <span class="relative flex size-2.5" aria-hidden="true">
                                <span v-if="visitors.last_5" class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60 motion-reduce:animate-none"></span>
                                <span class="relative inline-flex size-2.5 rounded-full" :class="visitors.last_5 ? 'bg-success' : 'bg-ink-4'"></span>
                            </span>
                            {{ visitors.last_5 }}
                        </p>
                        <p class="m-0 text-xs text-ink-3">{{ $t('arcadia.editor.settings.health.visitors.last_5') }}</p>
                    </div>
                    <div v-for="key in ['last_30', 'last_60', 'guests', 'signed_in', 'bots']" :key="key" class="rounded-lg border border-rule bg-well px-4 py-3">
                        <p class="m-0 font-mono text-3xl font-semibold tabular-nums text-ink">{{ visitors[key] }}</p>
                        <p class="m-0 text-xs text-ink-3">{{ $t(`arcadia.editor.settings.health.visitors.${key}`) }}</p>
                    </div>
                </div>
                <p v-else class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.visitors.unmeasurable', { driver: visitors.driver }) }}</p>
            </section>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- ── queue ── -->
                <section class="panel border p-5" :class="LEVEL[health.queue.level].ring">
                    <Eyebrow as="h2" class="mb-4 flex items-center gap-2">
                        <component :is="LEVEL[health.queue.level].icon" :size="14" :class="LEVEL[health.queue.level].text" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.health.queue.title') }}
                        <span class="font-mono normal-case tracking-normal text-ink-4">{{ health.queue.driver }}</span>
                    </Eyebrow>
                    <template v-if="health.queue.measurable">
                        <div class="flex gap-6">
                            <div><p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ health.queue.waiting }}</p><p class="m-0 text-xs text-ink-3">{{ $t('arcadia.editor.settings.health.queue.waiting') }}</p></div>
                            <div><p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ health.queue.running }}</p><p class="m-0 text-xs text-ink-3">{{ $t('arcadia.editor.settings.health.queue.running') }}</p></div>
                        </div>
                        <p class="m-0 mt-3 text-xs" :class="LEVEL[health.queue.level].text">
                            <template v-if="health.queue.level !== 'ok'">{{ $t('arcadia.editor.settings.health.queue.stuck') }} </template>
                            <template v-if="health.queue.oldest_seconds !== null">{{ $t('arcadia.editor.settings.health.queue.oldest', { age: age(health.queue.oldest_seconds) }) }}</template>
                            <template v-else>{{ $t('arcadia.editor.settings.health.queue.empty') }}</template>
                        </p>
                    </template>
                    <p v-else class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.queue.unmeasurable', { driver: health.queue.driver }) }}</p>
                </section>

                <!-- ── worker ── -->
                <section class="panel border p-5" :class="LEVEL[health.worker.level].ring">
                    <Eyebrow as="h2" class="mb-4 flex items-center gap-2">
                        <component :is="LEVEL[health.worker.level].icon" :size="14" :class="LEVEL[health.worker.level].text" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.health.worker.title') }}
                    </Eyebrow>
                    <template v-if="health.worker.last_seen">
                        <p class="m-0 text-sm text-ink">{{ $t('arcadia.editor.settings.health.worker.last_seen', { ago: age(health.worker.seconds_ago) }) }}</p>
                        <p class="m-0 mt-1 font-mono text-xs text-ink-4">{{ when(health.worker.last_seen) }}</p>
                        <p v-if="health.worker.level === 'error'" class="m-0 mt-3 text-xs text-danger">{{ $t('arcadia.editor.settings.health.worker.stale') }}</p>
                    </template>
                    <p v-else class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.worker.never') }}</p>
                </section>
            </div>

            <!-- ── failed jobs ── -->
            <section class="panel border p-5" :class="LEVEL[health.failed_jobs.level].ring">
                <Eyebrow as="h2" class="mb-4 flex items-center gap-2">
                    <component :is="LEVEL[health.failed_jobs.level].icon" :size="14" :class="LEVEL[health.failed_jobs.level].text" aria-hidden="true" />
                    {{ $t('arcadia.editor.settings.health.failed.title') }}
                    <span v-if="health.failed_jobs.count" class="font-mono normal-case tracking-normal text-danger">{{ $t('arcadia.editor.settings.health.failed.count', { count: health.failed_jobs.count }) }}</span>
                </Eyebrow>

                <template v-if="health.failed_jobs.count">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <DateRangeFilter :model-value="filters.failed" :loading="filtering" @update:model-value="applyFilter" />
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.showing', { shown: health.failed_jobs.jobs.length, total: health.failed_jobs.in_range }) }}</span>
                            <!-- acts on the filtered set, so it sits with the filter and names its count -->
                            <Button type="button" variant="ghost-danger" size="sm" :disabled="!!busy || !health.failed_jobs.in_range" :title="scope()" @click="flushing = true">
                                <Eraser :size="14" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.failed.flush', { count: health.failed_jobs.in_range }) }}
                            </Button>
                        </div>
                    </div>

                    <p v-if="!health.failed_jobs.jobs.length" class="m-0 rounded-lg bg-well px-3 py-2.5 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.filter.empty') }}</p>
                    <ul v-else class="m-0 grid max-h-96 grid-cols-1 list-none gap-2 overflow-y-auto overscroll-contain p-0 pr-1">
                        <li v-for="job in health.failed_jobs.jobs" :key="job.uuid" class="flex flex-wrap items-start gap-3 rounded-lg bg-well px-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="m-0 font-mono text-xs font-semibold text-ink">{{ job.job }} <span class="font-normal text-ink-4">· {{ job.queue }} · {{ when(job.failed_at) }}</span></p>
                                <p class="m-0 mt-1 break-words font-mono text-[11px] text-danger">{{ job.error }}</p>
                            </div>
                            <div class="flex gap-1.5">
                                <Button type="button" variant="outline" size="sm" class="h-7 px-2.5 text-xs" :disabled="!!busy" @click="retrying = job">
                                    <RotateCcw :size="12" aria-hidden="true" />
                                    {{ $t('arcadia.editor.settings.health.failed.retry') }}
                                </Button>
                                <Button type="button" variant="ghost-danger" size="icon-sm" class="size-7" :disabled="!!busy" :aria-label="$t('arcadia.editor.settings.health.failed.forget')" @click="forgetting = job.uuid">
                                    <Trash2 :size="12" aria-hidden="true" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="health.failed_jobs.in_range > health.failed_jobs.jobs.length" class="m-0 mt-2 text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.filter.capped', { limit: health.failed_jobs.limit }) }}</p>
                </template>
                <p v-else class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.settings.health.failed.none') }}</p>
            </section>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- ── mail ── -->
                <section class="panel border p-5" :class="LEVEL[health.mail.level].ring">
                    <Eyebrow as="h2" class="mb-4 flex items-center gap-2">
                        <component :is="LEVEL[health.mail.level].icon" :size="14" :class="LEVEL[health.mail.level].text" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.health.mail.title') }}
                    </Eyebrow>
                    <dl class="m-0 mb-4 grid grid-cols-1 gap-2 text-xs">
                        <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t('arcadia.editor.settings.health.mail.mailer') }}</dt><dd class="m-0 font-mono text-ink">{{ health.mail.mailer }}</dd></div>
                        <div v-if="health.mail.host" class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t('arcadia.editor.settings.health.mail.host') }}</dt><dd class="m-0 truncate font-mono text-ink">{{ health.mail.host }}:{{ health.mail.port }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t('arcadia.editor.settings.health.mail.from') }}</dt><dd class="m-0 truncate font-mono text-ink">{{ health.mail.from || '—' }}</dd></div>
                    </dl>
                    <Button type="button" variant="outline" size="sm" :disabled="!!busy" @click="testMail">
                        <Spinner v-if="busy === 'mail'" />
                        <Send v-else :size="14" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.health.mail.test') }}
                    </Button>
                    <p class="m-0 mt-2 text-[11px] text-ink-4">{{ $t('arcadia.editor.settings.health.mail.test_hint') }}</p>
                </section>

                <!-- ── environment ── -->
                <section class="panel border p-5" :class="LEVEL[health.environment.level].ring">
                    <Eyebrow as="h2" class="mb-4 flex items-center gap-2">
                        <component :is="LEVEL[health.environment.level].icon" :size="14" :class="LEVEL[health.environment.level].text" aria-hidden="true" />
                        {{ $t('arcadia.editor.settings.health.environment.title') }}
                        <span class="font-mono normal-case tracking-normal text-ink-4">{{ health.environment.env }} · PHP {{ health.environment.php }} · Laravel {{ health.environment.laravel }}</span>
                    </Eyebrow>
                    <ul class="m-0 mb-4 grid grid-cols-1 list-none gap-1.5 p-0">
                        <li v-for="(level, check) in health.environment.checks" :key="check" class="flex items-center gap-2 text-xs text-ink-2">
                            <component :is="LEVEL[level].icon" :size="13" class="shrink-0" :class="LEVEL[level].text" aria-hidden="true" />
                            {{ $t(`arcadia.editor.settings.health.environment.checks.${check}`) }}
                            <span v-if="check === 'disk' && health.environment.disk.total" class="ml-auto font-mono text-[11px] text-ink-4">
                                {{ $t('arcadia.editor.settings.health.environment.disk_free', { free: size(health.environment.disk.free), total: size(health.environment.disk.total) }) }}
                            </span>
                            <span v-if="check === 'ssr'" class="ml-auto min-w-0 break-all text-right font-mono text-[11px] text-ink-4">{{ health.environment.ssr.url }}</span>
                        </li>
                    </ul>
                    <Eyebrow tone="muted" size="sm" class="mb-1.5">{{ $t('arcadia.editor.settings.health.environment.drivers') }}</Eyebrow>
                    <div class="flex flex-wrap gap-1.5">
                        <span v-for="(driver, name) in health.environment.drivers" :key="name" class="chip px-2.5 py-0.5 font-mono text-[11px] text-ink-2">{{ name }}: {{ driver }}</span>
                    </div>
                </section>
            </div>
        </div>

        <ConfirmDialog :open="!!retrying" :processing="busy === 'retry'"
                       :title="$t('arcadia.editor.settings.health.failed.retry_title', { job: retrying?.job ?? '' })"
                       :body="$t('arcadia.editor.settings.health.failed.retry_body')"
                       :confirm-label="$t('arcadia.editor.settings.health.failed.retry')"
                       @confirm="retry" @cancel="retrying = null" />

        <ConfirmDialog :open="!!forgetting" :processing="busy === 'forget'"
                       :title="$t('arcadia.editor.settings.health.failed.confirm_title')"
                       :body="$t('arcadia.editor.settings.health.failed.confirm_body')"
                       :confirm-label="$t('arcadia.editor.settings.health.failed.forget')"
                       @confirm="forget" @cancel="forgetting = null" />

        <ConfirmDialog :open="flushing" :processing="busy === 'flush'"
                       :title="$t('arcadia.editor.settings.health.failed.flush_title', { count: health.failed_jobs.in_range, scope: scope() })"
                       :body="[$t('arcadia.editor.settings.health.failed.flush_body'), health.failed_jobs.count > health.failed_jobs.in_range ? $t('arcadia.editor.settings.health.failed.flush_kept', { count: health.failed_jobs.count - health.failed_jobs.in_range }) : ''].join(' ').trim()"
                       :confirm-label="$t('arcadia.editor.settings.health.failed.flush', { count: health.failed_jobs.in_range })"
                       @confirm="flush" @cancel="flushing = false" />
    </div>
</template>
