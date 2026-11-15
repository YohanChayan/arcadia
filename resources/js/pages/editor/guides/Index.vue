<script setup>
/**
 * This site's guides, twelve to a page, one row each (`GuideRow`), and what can be done to many
 * of them at once: publish, set to draft, list in the catalogue or remove from it, or delete.
 *
 * A selection is either the guides ticked by hand, kept across pages, or every guide the filter
 * matches — across all its pages — less any unticked after; `picked` holds the ticked ids in
 * the first case and the unticked ones in the second. A new filter or search starts it over.
 * What the action would reach is confirmed in `GuideBulkDialog`, which asks the server.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Plus, BookOpen, ChevronDown, Send, FileMinus, Trash2, X, LibraryBig, EyeOff } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import SearchField from '@/components/common/SearchField.vue'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import GuideLinkDialog from '@/components/editor/GuideLinkDialog.vue'
import GuideRow from '@/components/editor/GuideRow.vue'
import GuideBulkDialog from '@/components/editor/GuideBulkDialog.vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'
import { trans } from 'laravel-vue-i18n'

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

// The row whose share link is open, read back from the list by id so a changed link shows at once.
const linkGuideId = ref(null)
const linkGuide = computed(() => props.guides.data.find(row => row.id === linkGuideId.value) ?? null)

const pendingDelete = ref(null)
const deleting = ref(false)

const confirmDelete = () => {
    deleting.value = true
    router.delete(editorRoute('guides.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => picked.value.delete(pendingDelete.value.id),
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}

// The selection: ticked ids, or — with `allMatching` — every match less the unticked ids.
const allMatching = ref(false)
const picked = ref(new Set())

const isSelected = id => allMatching.value !== picked.value.has(id)
const selectedCount = computed(() => allMatching.value ? props.guides.total - picked.value.size : picked.value.size)

const toggle = id => {
    picked.value.has(id) ? picked.value.delete(id) : picked.value.add(id)
    if (allMatching.value && picked.value.size >= props.guides.total) clearSelection()
}

const pageIds = computed(() => props.guides.data.map(guide => guide.id))
const pageState = computed(() => {
    const ticked = pageIds.value.filter(isSelected).length
    if (!ticked) return false
    return ticked === pageIds.value.length ? true : 'indeterminate'
})

// Ticks the page, or unticks it when all of it is already ticked.
const togglePage = () => {
    const select = pageState.value !== true
    pageIds.value.forEach(id => {
        if (isSelected(id) !== select) toggle(id)
    })
}

const selectAllMatching = () => {
    allMatching.value = true
    picked.value = new Set()
}

function clearSelection() {
    allMatching.value = false
    picked.value = new Set()
}

// What one action is asked to reach — sent to the preview, then to apply it, unchanged.
const bulkRequest = ref(null)

const askBulk = action => {
    bulkRequest.value = allMatching.value
        ? { action, all: true, ids: [], except: [...picked.value], status: props.status, search: props.search }
        : { action, all: false, ids: [...picked.value], except: [], status: null, search: null }
    fromRow.value = false
}

// A row's own publish goes through the same confirmation, for that one guide, and leaves the
// selection alone; a bulk action spends it.
const fromRow = ref(false)

const askPublish = guide => {
    bulkRequest.value = { action: 'publish', all: false, ids: [guide.id], except: [], status: null, search: null }
    fromRow.value = true
}

const bulkDone = () => {
    if (!fromRow.value) clearSelection()
    bulkRequest.value = null
}

const bulkActions = [
    { key: 'publish', icon: Send, tone: 'text-success' },
    { key: 'draft', icon: FileMinus, tone: '' },
]

// Whether the guides appear on the public catalogue, in a level of their own (ADR 0024).
const catalogueActions = [
    { key: 'list', icon: LibraryBig, tone: 'text-success' },
    { key: 'unlist', icon: EyeOff, tone: '' },
]

// Each choice with how many it would show, whatever is filtered now.
const filters = computed(() => [
    { value: null, label: trans('arcadia.editor.guides.filters.all'), count: Object.values(props.statusCounts).reduce((sum, n) => sum + n, 0) },
    ...props.statuses.map(s => ({ value: s.value, label: s.label, count: props.statusCounts[s.value] ?? 0 })),
])

const query = (overrides = {}) => {
    const params = { status: props.status, search: props.search, ...overrides }
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''))
}

// A new filter starts at its first page, with nothing selected.
const filterBy = status => {
    clearSelection()
    router.get(editorRoute('guides.index'), query({ status, page: undefined }), { preserveScroll: true })
}

// When the owner asks — Enter or the button — from the first page; the state is kept so the box keeps its focus.
const searchTerm = ref(props.search ?? '')

const search = term => {
    if (term === (props.search ?? '')) return

    clearSelection()
    router.get(editorRoute('guides.index'), query({ search: term, page: undefined }), { preserveState: true, preserveScroll: true })
}

// The selection is kept from page to page.
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

        <template v-else>
            <!-- The page's tick and, once anything is selected, what to do with it. -->
            <div role="toolbar" :aria-label="$t('arcadia.editor.guides.bulk.label')"
                 class="sticky top-0 z-20 mb-2 flex min-h-11 flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-rule bg-card/95 px-3 py-1.5 backdrop-blur">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-3">
                    <Checkbox :model-value="pageState" @update:model-value="togglePage" />
                    {{ $t('arcadia.editor.guides.bulk.select_page') }}
                </label>

                <template v-if="selectedCount">
                    <span class="h-5 w-px bg-rule" aria-hidden="true" />
                    <span class="text-sm font-medium text-ink" aria-live="polite">
                        {{ $tChoice('arcadia.editor.guides.bulk.selected', selectedCount, { count: selectedCount }) }}
                    </span>
                    <Button v-if="!allMatching && guides.total > selectedCount" type="button" variant="link" size="sm" class="h-auto px-0" @click="selectAllMatching">
                        {{ $t('arcadia.editor.guides.bulk.select_all', { count: guides.total }) }}
                    </Button>
                    <Button type="button" variant="ghost" size="sm" @click="clearSelection">
                        <X :size="14" aria-hidden="true" />
                        {{ $t('arcadia.editor.guides.bulk.clear') }}
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="ml-auto">
                                {{ $t('arcadia.editor.guides.bulk.actions') }}
                                <ChevronDown :size="14" aria-hidden="true" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>{{ $t('arcadia.editor.guides.bulk.label') }}</DropdownMenuLabel>
                            <DropdownMenuItem v-for="bulk in bulkActions" :key="bulk.key" @select="askBulk(bulk.key)">
                                <component :is="bulk.icon" :class="bulk.tone" aria-hidden="true" />
                                {{ $t(`arcadia.editor.guides.bulk.${bulk.key}`) }}
                            </DropdownMenuItem>
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <LibraryBig aria-hidden="true" />
                                    {{ $t('arcadia.editor.guides.bulk.catalogue') }}
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent>
                                    <DropdownMenuItem v-for="bulk in catalogueActions" :key="bulk.key" @select="askBulk(bulk.key)">
                                        <component :is="bulk.icon" :class="bulk.tone" aria-hidden="true" />
                                        {{ $t(`arcadia.editor.guides.bulk.${bulk.key}`) }}
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" @select="askBulk('delete')">
                                <Trash2 aria-hidden="true" />
                                {{ $t('arcadia.editor.guides.bulk.delete') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </div>

            <ul class="m-0 flex list-none flex-col gap-2 p-0">
                <GuideRow v-for="guide in guides.data" :key="guide.id"
                          :guide="guide" :selected="isSelected(guide.id)"
                          :level-label="levelFor(guide.level)" :status="statusFor(guide.status)"
                          @toggle="toggle(guide.id)" @link="linkGuideId = guide.id"
                          @publish="askPublish(guide)" @delete="pendingDelete = guide" />
            </ul>
        </template>

        <PaginationControls v-if="guides.last_page > 1" class="mt-6" :page="guides.current_page" :last-page="guides.last_page" @change="goToPage" />

        <GuideLinkDialog :guide="linkGuide" @close="linkGuideId = null" />

        <GuideBulkDialog :request="bulkRequest" @close="bulkRequest = null" @done="bulkDone" />

        <ConfirmDialog :open="pendingDelete !== null"
                       :title="$t('arcadia.editor.guides.delete.title')"
                       :body="$t('arcadia.editor.guides.delete.body')"
                       :confirm-label="$t('arcadia.editor.guides.delete.confirm')"
                       :processing="deleting"
                       @confirm="confirmDelete"
                       @cancel="pendingDelete = null" />
    </div>
</template>
