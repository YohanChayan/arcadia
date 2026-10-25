<script setup>
/**
 * This host's dashboard, where a sign-in lands. One shape for
 * every figure, so none is styled into looking more important than it is. What needs answering
 * first — new requests, one click from a reply — then the guides, and the price; then what each
 * guide's presentation brought in, over 30 days or since the start (ADR 0022).
 */
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { ExternalLink, Mail, MessageCircle, Plus } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    siteUrl: { type: String, required: true },
    stats: { type: Object, required: true },
    latestInquiries: { type: Array, required: true },
    recentGuides: { type: Array, required: true },
    // `30` or `all`: the window the presentation figures cover.
    window: { type: String, default: '30' },
    // [{ id, title, open_for_enrollment, visits, requests, enrollments }] — guides open for
    // enrollment or with any figure in the window.
    presentations: { type: Array, default: () => [] },
})

const editorRoute = useEditorRoute()

const groups = computed(() => [
    {
        key: 'inquiries',
        title: trans('arcadia.editor.home.inquiries.title'),
        href: editorRoute('inquiries.index'),
        tiles: [
            { label: trans('arcadia.editor.home.inquiries.pending'), value: props.stats.inquiries.pending, tone: 'text-accent' },
            { label: trans('arcadia.editor.home.inquiries.total'), value: props.stats.inquiries.total, tone: 'text-ink-3' },
        ],
    },
    {
        key: 'guides',
        title: trans('arcadia.editor.home.guides.title'),
        href: editorRoute('guides.index'),
        tiles: [
            { label: trans('arcadia.editor.home.guides.published'), value: props.stats.guides.published, tone: 'text-success' },
            { label: trans('arcadia.editor.home.guides.drafts'), value: props.stats.guides.drafts, tone: 'text-ink' },
            // Someone waiting to pay: answered on each guide's Readers screen.
            { label: trans('arcadia.editor.home.guides.enrollments'), value: props.stats.enrollments.pending, tone: props.stats.enrollments.pending ? 'text-signal' : 'text-ink-3' },
        ],
    },
    {
        key: 'price',
        title: trans('arcadia.editor.home.price.title'),
        href: editorRoute('settings.edit'),
        tiles: [
            { label: trans('arcadia.editor.home.price.per'), value: props.stats.price, tone: 'text-ink' },
        ],
    },
])

const setWindow = value => router.get(editorRoute('home'), value === 'all' ? { window: 'all' } : {}, { preserveScroll: true, preserveState: true })

const formatDate = iso => new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.home.title')]" />

    <div class="py-6">
        <PageHeader :title="$t('arcadia.editor.home.title')" :description="$t('arcadia.editor.home.lead')">
            <template #actions>
                <a :href="siteUrl" target="_blank" rel="noopener noreferrer" :class="buttonVariants({ variant: 'outline' })" class="no-underline">
                    <ExternalLink :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.home.view_site') }}
                </a>
                <Link :href="editorRoute('guides.create')" :class="buttonVariants({ variant: 'brand' })" class="no-underline">
                    <Plus :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.guides.new') }}
                </Link>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <section v-for="group in groups" :key="group.key" class="panel p-5">
                <div class="mb-4 flex items-baseline justify-between">
                    <Eyebrow as="h2">{{ group.title }}</Eyebrow>
                    <Link :href="group.href" class="text-[11px] text-ink-4 no-underline transition-colors hover:text-accent">
                        {{ $t('arcadia.editor.home.open') }}
                    </Link>
                </div>

                <div class="grid gap-2" :class="['grid-cols-1', 'grid-cols-2', 'grid-cols-3'][Math.min(group.tiles.length, 3) - 1]">
                    <div v-for="tile in group.tiles" :key="tile.label" class="rounded-lg border border-rule bg-well px-3 py-2.5 text-center">
                        <p class="font-mono text-lg font-semibold tabular-nums" :class="tile.tone">{{ tile.value }}</p>
                        <p class="mt-1 text-xs leading-tight text-ink-3">{{ tile.label }}</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- What each guide's presentation brought in: visits to its link's page, requests, approvals. -->
        <section class="panel mb-6 p-5">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
                <Eyebrow as="h2">{{ $t('arcadia.editor.home.presentations.title') }}</Eyebrow>
                <div class="flex gap-1" role="group" :aria-label="$t('arcadia.editor.home.presentations.window')">
                    <Button v-for="option in ['30', 'all']" :key="option" type="button" size="sm"
                            :variant="window === option ? 'outline' : 'ghost'" :aria-pressed="window === option"
                            @click="setWindow(option)">
                        {{ $t(`arcadia.editor.home.presentations.windows.${option}`) }}
                    </Button>
                </div>
            </div>

            <p v-if="!presentations.length" class="m-0 py-6 text-center text-sm text-ink-4">{{ $t('arcadia.editor.home.presentations.empty') }}</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-ink-4">
                            <th class="py-2 pr-3 font-medium">{{ $t('arcadia.editor.home.presentations.guide') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ $t('arcadia.editor.home.presentations.visits') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ $t('arcadia.editor.home.presentations.requests') }}</th>
                            <th class="py-2 pl-3 text-right font-medium">{{ $t('arcadia.editor.home.presentations.enrollments') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule">
                        <tr v-for="row in presentations" :key="row.id">
                            <td class="py-2.5 pr-3">
                                <Link :href="editorRoute('readers.index', { guide: row.id })" class="font-medium text-ink no-underline hover:text-accent">{{ row.title }}</Link>
                                <Badge v-if="!row.open_for_enrollment" variant="neutral" class="ml-2">{{ $t('arcadia.editor.home.presentations.closed') }}</Badge>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono tabular-nums text-ink">{{ row.visits }}</td>
                            <td class="px-3 py-2.5 text-right font-mono tabular-nums text-ink">{{ row.requests }}</td>
                            <td class="py-2.5 pl-3 text-right font-mono tabular-nums text-success">{{ row.enrollments }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 mb-0 text-xs text-ink-4">{{ $t('arcadia.editor.home.presentations.hint') }}</p>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="panel p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <Eyebrow as="h2">{{ $t('arcadia.editor.home.latest_inquiries') }}</Eyebrow>
                    <Link :href="editorRoute('inquiries.index')" class="text-[11px] text-ink-4 no-underline transition-colors hover:text-accent">
                        {{ $t('arcadia.editor.home.open') }}
                    </Link>
                </div>

                <p v-if="!latestInquiries.length" class="m-0 py-6 text-center text-sm text-ink-4">{{ $t('arcadia.editor.inquiries.empty.hint') }}</p>
                <ul v-else class="m-0 divide-y divide-rule p-0">
                    <li v-for="inquiry in latestInquiries" :key="inquiry.id" class="flex list-none items-start justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="m-0 truncate text-sm text-ink" :class="inquiry.stage === 'pending' ? 'font-semibold' : ''">{{ inquiry.topic }}</p>
                            <a :href="inquiry.reply_url" v-bind="inquiry.contact_kind === 'whatsapp' ? { target: '_blank', rel: 'noopener noreferrer' } : {}"
                               class="mt-0.5 inline-flex items-center gap-1 text-xs text-accent no-underline hover:underline">
                                <component :is="inquiry.contact_kind === 'whatsapp' ? MessageCircle : Mail" :size="12" aria-hidden="true" />
                                {{ inquiry.name ? `${inquiry.name} · ${inquiry.contact}` : inquiry.contact }}
                            </a>
                        </div>
                        <div class="shrink-0 text-right">
                            <Badge v-if="inquiry.stage === 'pending'" variant="pending">{{ $t('arcadia.editor.inquiries.stages.pending') }}</Badge>
                            <p class="mt-1 mb-0 font-mono text-[11px] text-ink-4">{{ formatDate(inquiry.created_at) }}</p>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="panel p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <Eyebrow as="h2">{{ $t('arcadia.editor.home.recent_guides') }}</Eyebrow>
                    <Link :href="editorRoute('guides.index')" class="text-[11px] text-ink-4 no-underline transition-colors hover:text-accent">
                        {{ $t('arcadia.editor.home.open') }}
                    </Link>
                </div>

                <p v-if="!recentGuides.length" class="m-0 py-6 text-center text-sm text-ink-4">{{ $t('arcadia.editor.guides.empty.hint') }}</p>
                <ul v-else class="m-0 divide-y divide-rule p-0">
                    <li v-for="guide in recentGuides" :key="guide.id" class="flex list-none items-center justify-between gap-3 py-3">
                        <Link :href="editorRoute('content.show', { guide: guide.id })" class="min-w-0 truncate text-sm font-medium text-ink no-underline hover:text-accent">
                            {{ guide.title }}
                        </Link>
                        <Badge :variant="guide.status === 'published' ? 'ok' : 'neutral'" class="shrink-0">
                            {{ $t(`arcadia.editor.guides.statuses.${guide.status}`) }}
                        </Badge>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
