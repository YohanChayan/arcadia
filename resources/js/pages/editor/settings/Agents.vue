<script setup>
/**
 * Site settings → Agents: what the sales assistant and the knowledge index have been doing, as
 * counts and metrics (`AgentActivity`). Never what was said — no message, reply or passage text,
 * and nothing live: it is read when the tab opens, or on Refresh. Site-wide: both editors show
 * the same.
 *
 * The assistant's figures follow the date filter, defaulting to the last 24 hours, in the query
 * string. The index is a state, not a stream, and ignores it. A failed turn keeps no reason: the
 * card points to Logs, at the turn's time.
 */
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Bot, CircleCheck, CircleAlert, CircleX, Database, RefreshCw } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import SiteSettingsTabs from '@/components/editor/SiteSettingsTabs.vue'
import DateRangeFilter from '@/components/editor/settings/DateRangeFilter.vue'
import { Button } from '@/components/ui/button'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    assistant: { type: Object, required: true },
    knowledge: { type: Object, required: true },
    filter: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const LEVEL = {
    ok: { icon: CircleCheck, text: 'text-success', ring: 'border-rule' },
    warn: { icon: CircleAlert, text: 'text-signal', ring: 'border-signal/40' },
    error: { icon: CircleX, text: 'text-danger', ring: 'border-danger/40' },
}

const number = value => value === null || value === undefined ? '—' : new Intl.NumberFormat().format(value)
const when = iso => iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const usd = value => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 4 }).format(value)

const base = 'arcadia.editor.settings.agents'

const tiles = computed(() => [
    { key: 'conversations', value: number(props.assistant.conversations.total) },
    { key: 'turns', value: number(props.assistant.turns.total) },
    { key: 'conversions', value: number(props.assistant.conversions) },
    { key: 'engagement', value: props.assistant.engagement === null ? '—' : `${props.assistant.engagement}` },
    { key: 'reply_time', value: props.assistant.usage.average_seconds === null ? '—' : `${props.assistant.usage.average_seconds}s` },
])

const current = ref({ ...props.filter })
const loading = ref(false)

const query = () => ({
    agents_range: current.value.range,
    ...(current.value.range === 'custom' ? { agents_from: current.value.from, agents_to: current.value.to } : {}),
})

const reload = () => router.reload({
    data: query(),
    only: ['assistant', 'knowledge', 'filter'],
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
    <EditorTitle :parts="[$t('arcadia.editor.settings.title'), $t('arcadia.editor.settings.tabs.agents')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.settings.title') }]" />

        <PageHeader :title="$t('arcadia.editor.settings.title')" :description="$t('arcadia.editor.settings.description')" />

        <SiteSettingsTabs active="agents" />

        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <p class="m-0 max-w-prose text-xs text-ink-3">{{ $t(`${base}.hint`) }}</p>
                <Button type="button" variant="outline" size="sm" :disabled="loading" @click="reload">
                    <RefreshCw :size="14" :class="loading ? 'animate-spin' : ''" aria-hidden="true" /> {{ $t('arcadia.editor.settings.health.refresh') }}
                </Button>
            </div>

            <!-- ── the sales assistant ── -->
            <section class="panel border p-5" :class="LEVEL[assistant.level].ring">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <Eyebrow as="h2" class="flex items-center gap-2">
                        <component :is="LEVEL[assistant.level].icon" :size="14" :class="LEVEL[assistant.level].text" aria-hidden="true" />
                        <Bot :size="14" aria-hidden="true" />
                        {{ $t(`${base}.assistant.title`) }}
                    </Eyebrow>
                    <DateRangeFilter :model-value="current" :loading="loading" @update:model-value="applyFilter" />
                </div>

                <dl class="m-0 mb-4 grid grid-cols-1 gap-x-6 gap-y-2 text-xs sm:grid-cols-2">
                    <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.provider`) }}</dt><dd class="m-0 font-mono text-ink">{{ assistant.provider }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-3">{{ $t(`${base}.assistant.key`) }}</dt>
                        <dd class="m-0 font-mono" :class="assistant.key_present ? 'text-success' : 'text-danger'">{{ $t(`${base}.assistant.${assistant.key_present ? 'key_present' : 'key_missing'}`) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.planner`) }}</dt><dd class="m-0 truncate font-mono text-ink">{{ assistant.models.planner }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.speaker`) }}</dt><dd class="m-0 truncate font-mono text-ink">{{ assistant.models.speaker }}</dd></div>
                </dl>

                <p v-if="!assistant.key_present" class="m-0 mb-4 text-xs text-danger">{{ $t(`${base}.assistant.key_missing_hint`) }}</p>
                <p v-if="assistant.turns.stuck" class="m-0 mb-4 text-xs text-signal">
                    {{ $t(`${base}.assistant.stuck`, { count: assistant.turns.stuck, minutes: assistant.turns.stuck_minutes }) }}
                </p>

                <p v-if="!assistant.conversations.total && !assistant.turns.total" class="m-0 rounded-lg bg-well px-3 py-2.5 text-sm text-ink-3">{{ $t(`${base}.assistant.none`) }}</p>
                <template v-else>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <div v-for="tile in tiles" :key="tile.key" class="rounded-lg border border-rule bg-well px-4 py-3">
                            <p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ tile.value }}</p>
                            <p class="m-0 text-xs text-ink-3">{{ $t(`${base}.assistant.${tile.key}`) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <!-- turns by outcome -->
                        <div>
                            <Eyebrow as="h3" class="mb-2">{{ $t(`${base}.assistant.turns`) }}</Eyebrow>
                            <dl class="m-0 grid grid-cols-1 gap-1.5 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.done`) }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(assistant.turns.done) }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.failed`) }}</dt><dd class="m-0 font-mono tabular-nums" :class="assistant.turns.failed ? 'text-signal' : 'text-ink'">{{ number(assistant.turns.failed) }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.replying`) }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(assistant.turns.replying) }}</dd></div>
                            </dl>
                            <p v-if="assistant.turns.failed" class="m-0 mt-2 text-[11px] text-ink-4">
                                {{ $t(`${base}.assistant.failed_hint`) }}
                                <Link :href="editorRoute('settings.logs')" class="text-ink-2 underline">{{ $t('arcadia.editor.settings.tabs.logs') }}</Link>
                            </p>
                        </div>

                        <!-- tokens and cost -->
                        <div>
                            <Eyebrow as="h3" class="mb-2">{{ $t(`${base}.assistant.tokens`) }}</Eyebrow>
                            <dl class="m-0 grid grid-cols-1 gap-1.5 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.input`) }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(assistant.usage.input_tokens) }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.cached`) }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(assistant.usage.cached_input_tokens) }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.assistant.output`) }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(assistant.usage.output_tokens) }}</dd></div>
                                <div class="flex justify-between gap-3 border-t border-rule pt-1.5">
                                    <dt class="text-ink-3">{{ $t(`${base}.assistant.cost`) }}</dt>
                                    <dd class="m-0 font-mono tabular-nums" :class="assistant.cost === null ? 'text-ink-4' : 'text-ink'">{{ assistant.cost === null ? $t(`${base}.assistant.no_prices`) : usd(assistant.cost) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- where and with what -->
                        <div class="space-y-3">
                            <div>
                                <Eyebrow as="h3" class="mb-2">{{ $t(`${base}.assistant.channels`) }}</Eyebrow>
                                <dl class="m-0 grid grid-cols-1 gap-1.5 text-xs">
                                    <div v-for="(total, channel) in assistant.conversations.by_channel" :key="channel" class="flex justify-between gap-3"><dt class="font-mono text-ink-3">{{ channel }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(total) }}</dd></div>
                                </dl>
                            </div>
                            <div v-if="Object.keys(assistant.by_model).length">
                                <Eyebrow as="h3" class="mb-2">{{ $t(`${base}.assistant.models`) }}</Eyebrow>
                                <dl class="m-0 grid grid-cols-1 gap-1.5 text-xs">
                                    <div v-for="(total, model) in assistant.by_model" :key="model" class="flex justify-between gap-3"><dt class="truncate font-mono text-ink-3">{{ model }}</dt><dd class="m-0 font-mono tabular-nums text-ink">{{ number(total) }}</dd></div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            <!-- ── the knowledge index ── -->
            <section class="panel border p-5" :class="LEVEL[knowledge.level].ring">
                <Eyebrow as="h2" class="mb-1 flex items-center gap-2">
                    <component :is="LEVEL[knowledge.level].icon" :size="14" :class="LEVEL[knowledge.level].text" aria-hidden="true" />
                    <Database :size="14" aria-hidden="true" />
                    {{ $t(`${base}.knowledge.title`) }}
                </Eyebrow>
                <p class="m-0 mb-4 text-[11px] text-ink-4">{{ $t(`${base}.knowledge.not_windowed`) }}</p>

                <p v-if="!knowledge.reachable" class="m-0 mb-4 text-sm text-danger">{{ $t(`${base}.knowledge.unreachable`) }}</p>
                <template v-else>
                    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-rule bg-well px-4 py-3">
                            <p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ number(knowledge.chunks) }}</p>
                            <p class="m-0 text-xs text-ink-3">{{ $t(`${base}.knowledge.chunks`) }}</p>
                        </div>
                        <div class="rounded-lg border border-rule bg-well px-4 py-3">
                            <p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ number(knowledge.listed) }}</p>
                            <p class="m-0 text-xs text-ink-3">{{ $t(`${base}.knowledge.listed`) }}</p>
                        </div>
                        <div v-for="(total, locale) in knowledge.by_locale" :key="locale" class="rounded-lg border border-rule bg-well px-4 py-3">
                            <p class="m-0 font-mono text-2xl font-semibold tabular-nums text-ink">{{ number(total) }}</p>
                            <p class="m-0 font-mono text-xs uppercase text-ink-3">{{ locale }}</p>
                        </div>
                    </div>
                    <p v-if="knowledge.chunks === 0" class="m-0 mb-4 text-xs text-signal">{{ $t(`${base}.knowledge.empty`) }}</p>
                    <p v-if="knowledge.other_models.length" class="m-0 mb-4 text-xs text-signal">{{ $t(`${base}.knowledge.other_models`, { models: knowledge.other_models.join(', ') }) }}</p>
                </template>

                <dl class="m-0 grid grid-cols-1 gap-x-6 gap-y-2 text-xs sm:grid-cols-2">
                    <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.knowledge.embedding_model`) }}</dt><dd class="m-0 truncate font-mono text-ink">{{ knowledge.embedding_model }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ $t(`${base}.knowledge.last_indexed`) }}</dt><dd class="m-0 font-mono text-ink">{{ when(knowledge.last_indexed) }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-3">{{ $t(`${base}.knowledge.queue`, { queue: knowledge.queue }) }}</dt>
                        <dd class="m-0 font-mono tabular-nums text-ink">
                            {{ number(knowledge.pending) }} {{ $t(`${base}.knowledge.pending`) }}
                            · <span :class="knowledge.failed ? 'text-danger' : ''">{{ number(knowledge.failed) }} {{ $t(`${base}.knowledge.failed`) }}</span>
                        </dd>
                    </div>
                </dl>
                <p v-if="knowledge.failed" class="m-0 mt-2 text-[11px] text-ink-4">
                    {{ $t(`${base}.knowledge.failed_hint`) }}
                    <Link :href="editorRoute('settings.health')" class="text-ink-2 underline">{{ $t('arcadia.editor.settings.tabs.health') }}</Link>
                </p>
            </section>
        </div>
    </div>
</template>
