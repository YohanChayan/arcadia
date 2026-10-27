<script setup>
/**
 * This site's guides, twelve to a page, as compact cards: the cover across the top under a dark wash
 * with what the guide is and its status over it, edit hanging off the card's corner; then one row
 * of its counts — released versions among them — a line of what its link's page brought in over
 * 30 days, and its other actions along the bottom.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { Plus, Pencil, Trash2, ListTree, Link2, Users, Image as ImageIcon, BookOpen } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import SearchField from '@/components/common/SearchField.vue'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardFooter } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import GuideLinkDialog from '@/components/editor/GuideLinkDialog.vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'
import { cn } from '@/lib/utils'

const props = defineProps({
    guides: { type: Object, required: true },
    levels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    // The status filtered by, or null for every guide.
    status: { type: String, default: null },
    // The words searched for, or null for no search.
    search: { type: String, default: null },
    statusCounts: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const levelFor = value => props.levels.find(l => l.value === value)?.label ?? value
const statusFor = value => props.statuses.find(s => s.value === value) ?? { label: value, tone: 'neutral' }

// What its link's page brought in, as the editor home counts it; enrolled in the success tone.
const presentationFor = guide => [
    { key: 'visits', value: guide.presentation.visits, tone: 'text-ink' },
    { key: 'requests', value: guide.presentation.requests, tone: 'text-ink' },
    { key: 'enrollments', value: guide.presentation.enrollments, tone: 'text-success' },
]

// Released in the success tone while any is, so a guide with nothing out for readers stands out.
const metricsFor = guide => [
    { key: 'readers', value: guide.readers_count },
    { key: 'goals', value: guide.goals?.length ?? 0 },
    { key: 'versions', value: guide.versions_count },
    { key: 'released', value: guide.released_versions_count, tone: guide.released_versions_count ? 'text-success' : 'text-ink-4' },
]

// The footer's links; edit sits in the card's corner instead.
const actionsFor = guide => [
    { key: 'content', href: editorRoute('content.show', { guide: guide.id }), icon: ListTree, label: trans('arcadia.editor.content.action') },
    { key: 'readers', href: editorRoute('readers.index', { guide: guide.id }), icon: Users, label: trans('arcadia.editor.readers.action') },
    { key: 'images', href: editorRoute('images.index', { guide: guide.id }), icon: ImageIcon, label: trans('arcadia.editor.images.action') },
]

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

// The row whose share link is open, read back from the list by id so a changed link shows at once.
const linkGuideId = ref(null)
const linkGuide = computed(() => props.guides.data.find(row => row.id === linkGuideId.value) ?? null)

const pendingDelete = ref(null)
const deleting = ref(false)

const confirmDelete = () => {
    deleting.value = true
    router.delete(editorRoute('guides.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}

// Each choice with how many it would show, whatever is filtered now.
const filters = computed(() => [
    { value: null, label: trans('arcadia.editor.guides.filters.all'), count: Object.values(props.statusCounts).reduce((sum, n) => sum + n, 0) },
    ...props.statuses.map(s => ({ value: s.value, label: s.label, count: props.statusCounts[s.value] ?? 0 })),
])

const query = (overrides = {}) => {
    const params = { status: props.status, search: props.search, ...overrides }
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''))
}

// A new filter starts at its first page.
const filterBy = status => router.get(editorRoute('guides.index'), query({ status, page: undefined }), { preserveScroll: true })

// When the owner asks — Enter or the button — from the first page; the state is kept so the box keeps its focus.
const searchTerm = ref(props.search ?? '')

const search = term => {
    if (term === (props.search ?? '')) return

    router.get(editorRoute('guides.index'), query({ search: term, page: undefined }), { preserveState: true, preserveScroll: true })
}

const goToPage = page => router.get(editorRoute('guides.index'), query({ page }), { preserveState: true, preserveScroll: true })
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.guides.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title') }]" />

        <PageHeader :title="$t('arcadia.editor.guides.title')" :description="$t('arcadia.editor.guides.description')" :count="guides.total">
            <template #actions>
                <Link :href="editorRoute('guides.create')" :class="buttonVariants({ variant: 'brand' })" class="no-underline">
                    <Plus :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.guides.new') }}
                </Link>
            </template>
        </PageHeader>

        <SearchField v-model="searchTerm" class="mb-3 max-w-md"
                     :label="$t('arcadia.editor.guides.search.label')" :button-label="$t('arcadia.editor.guides.search.submit')"
                     @search="search" />

        <div role="group" :aria-label="$t('arcadia.editor.guides.filters.label')" class="mb-4 flex flex-wrap gap-1">
            <Button v-for="filter in filters" :key="filter.value ?? 'all'" type="button" size="sm"
                    :variant="status === filter.value ? 'secondary' : 'ghost'"
                    :aria-pressed="status === filter.value"
                    @click="filterBy(filter.value)">
                {{ filter.label }}
                <span class="font-mono text-xs tabular-nums text-ink-4">{{ filter.count }}</span>
            </Button>
        </div>

        <Empty v-if="!guides.data.length" class="border border-dashed border-rule">
            <EmptyHeader>
                <EmptyMedia variant="icon"><BookOpen /></EmptyMedia>
                <EmptyTitle>{{ search ? $t('arcadia.editor.guides.search.empty') : status ? $t('arcadia.editor.guides.filters.empty') : $t('arcadia.editor.guides.empty.title') }}</EmptyTitle>
                <EmptyDescription v-if="!status && !search">{{ $t('arcadia.editor.guides.empty.hint') }}</EmptyDescription>
            </EmptyHeader>
        </Empty>

        <ul v-else class="m-0 grid list-none gap-3 p-0 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <li v-for="guide in guides.data" :key="guide.id" class="relative min-w-0">
                <!-- Edit hangs off the card's top-right corner, clear of the title. -->
                <Link :href="editorRoute('guides.edit', guide.id)"
                      :class="cn(buttonVariants({ variant: 'outline', size: 'icon-sm' }), 'absolute -top-2 -right-2 z-10 rounded-full bg-card no-underline shadow-sm dark:bg-card dark:hover:bg-hover')"
                      :aria-label="$t('arcadia.editor.guides.edit')" :title="$t('arcadia.editor.guides.edit')">
                    <Pencil :size="14" aria-hidden="true" />
                </Link>
                <Card class="h-full gap-0 overflow-hidden py-0">
                    <!-- The cover across the top under a dark wash, so what the guide is reads over any image. -->
                    <div class="relative aspect-[1200/630] border-b border-rule bg-raised">
                        <img v-if="guide.cover" :src="guide.cover" alt="" width="1200" height="630" loading="lazy" class="absolute inset-0 size-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/20" aria-hidden="true" />
                        <!-- The whole cover leads to its images; the title above it leads to the content. -->
                        <Link :href="editorRoute('images.index', { guide: guide.id })"
                              class="absolute inset-0 flex items-center justify-center text-white/60 no-underline"
                              :aria-label="$t('arcadia.editor.images.action')" :title="$t('arcadia.editor.images.action')">
                            <ImageIcon v-if="!guide.cover" :size="24" aria-hidden="true" />
                        </Link>
                        <Badge :variant="statusFor(guide.status).tone" class="absolute top-2 left-2">{{ statusFor(guide.status).label }}</Badge>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 p-3">
                            <p class="m-0 truncate font-mono text-[11px] uppercase tracking-widest text-white/75">
                                {{ guide.category?.name ?? '—' }} · {{ levelFor(guide.level) }}
                            </p>
                            <Link :href="editorRoute('content.show', { guide: guide.id })" :title="guide.title"
                                  class="pointer-events-auto mt-0.5 line-clamp-2 font-display text-sm font-semibold leading-snug text-white no-underline hover:underline">
                                {{ guide.title }}
                            </Link>
                        </div>
                    </div>

                    <CardContent class="flex flex-1 flex-col gap-3 p-3">
                        <!-- The two switches a reader needs — published, and a released version — when they disagree. -->
                        <p v-if="guide.status === 'published' && !guide.has_release" class="m-0 text-xs text-warning">
                            {{ $t('arcadia.editor.guides.state.no_release') }}
                        </p>
                        <p v-else-if="guide.status === 'draft' && guide.has_release" class="m-0 text-xs text-ink-4">
                            {{ $t('arcadia.editor.guides.state.released_draft') }}
                        </p>

                        <!-- The guide's own counts in one row, what its link's page brought in as one line below. -->
                        <div class="mt-auto">
                            <dl class="m-0 grid grid-cols-4 gap-2 rounded-lg border border-rule px-2.5 py-1.5">
                                <div v-for="metric in metricsFor(guide)" :key="metric.key" class="min-w-0">
                                    <dt class="truncate text-[11px] text-ink-4">{{ $t(`arcadia.editor.guides.columns.${metric.key}`) }}</dt>
                                    <dd class="m-0 font-mono text-sm tabular-nums" :class="metric.tone ?? 'text-ink'">{{ metric.value }}</dd>
                                </div>
                            </dl>
                            <p class="m-0 mt-1.5 flex flex-wrap items-baseline gap-x-2 px-0.5 text-[11px] text-ink-4">
                                <span>{{ $t('arcadia.editor.guides.presentation') }}</span>
                                <span v-for="figure in presentationFor(guide)" :key="figure.key">
                                    <span class="font-mono tabular-nums" :class="figure.tone">{{ figure.value }}</span>
                                    {{ $t(`arcadia.editor.home.presentations.${figure.key}`).toLowerCase() }}
                                </span>
                                <span v-if="!guide.open_for_enrollment">· {{ $t('arcadia.editor.home.presentations.closed') }}</span>
                            </p>
                        </div>
                    </CardContent>

                    <!-- Icons alone, each named for a screen reader and in its tooltip; the dates fill the gap before delete. -->
                    <CardFooter class="gap-0.5 border-t border-rule px-1.5 py-1">
                        <Link v-for="action in actionsFor(guide)" :key="action.key" :href="action.href"
                              :class="buttonVariants({ variant: 'ghost', size: 'icon-sm' })" class="no-underline"
                              :aria-label="action.label" :title="action.label">
                            <component :is="action.icon" :size="16" aria-hidden="true" />
                        </Link>
                        <Button type="button" variant="ghost" size="icon-sm" @click="linkGuideId = guide.id" :class="guide.readable ? 'text-accent' : 'text-ink-4'"
                                :aria-label="$t('arcadia.editor.guides.link.action')" :title="$t('arcadia.editor.guides.link.action')">
                            <Link2 :size="16" aria-hidden="true" />
                        </Button>
                        <span class="ml-auto min-w-0 truncate px-1 font-mono text-[11px] text-ink-4"
                              :title="`${$t('arcadia.editor.guides.columns.created')} ${formatDate(guide.created_at)} · ${$t('arcadia.editor.guides.columns.updated')} ${formatDate(guide.updated_at)}`">
                            {{ $t('arcadia.editor.guides.columns.updated') }} {{ formatDate(guide.updated_at) }}
                        </span>
                        <Button type="button" variant="ghost-danger" size="icon-sm" class="shrink-0" @click="pendingDelete = guide"
                                :aria-label="$t('arcadia.editor.guides.delete.action')" :title="$t('arcadia.editor.guides.delete.action')">
                            <Trash2 :size="16" aria-hidden="true" />
                        </Button>
                    </CardFooter>
                </Card>
            </li>
        </ul>

        <PaginationControls v-if="guides.last_page > 1" class="mt-6" :page="guides.current_page" :last-page="guides.last_page" @change="goToPage" />

        <GuideLinkDialog :guide="linkGuide" @close="linkGuideId = null" />

        <ConfirmDialog :open="pendingDelete !== null"
                       :title="$t('arcadia.editor.guides.delete.title')"
                       :body="$t('arcadia.editor.guides.delete.body')"
                       :confirm-label="$t('arcadia.editor.guides.delete.confirm')"
                       :processing="deleting"
                       @confirm="confirmDelete"
                       @cancel="pendingDelete = null" />
    </div>
</template>
