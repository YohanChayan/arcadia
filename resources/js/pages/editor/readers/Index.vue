<script setup>
/**
 * Everyone this language's guides are for, one row per email, twenty to a page, searchable, and
 * filtered by access: every reader, those with an account, or those pending — given a guide but
 * never signed in, so never opened it. A row opens the dialog that lists its guides. Readers are added and removed on each guide's own readers screen.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { Clock, Eye, UserRound, Users } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import DataTable from '@/components/common/DataTable.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import SearchField from '@/components/common/SearchField.vue'
import ReaderGuidesDialog from '@/components/editor/ReaderGuidesDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // Paginated: [{ email, name, has_account, guides_count, last_assigned_at, guides: [...] }]
    readers: { type: Object, required: true },
    // { readers, with_account, pending }, whatever the search and the filter.
    totals: { type: Object, required: true },
    statuses: { type: Array, required: true },
    // { search, access }, each null for none; access is `account` or `pending`.
    filters: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const columns = computed(() => [
    { key: 'reader', label: trans('arcadia.editor.reader_directory.columns.reader') },
    { key: 'account', label: trans('arcadia.editor.reader_directory.columns.account') },
    { key: 'guides', label: trans('arcadia.editor.reader_directory.columns.guides'), align: 'right' },
    { key: 'actions', label: '', align: 'right', width: '1%' },
])

const showing = ref(null)

const accessTabs = computed(() => [
    { value: null, key: 'all', icon: Users, count: props.totals.readers },
    { value: 'account', key: 'account', icon: UserRound, count: props.totals.with_account },
    { value: 'pending', key: 'pending', icon: Clock, count: props.totals.pending },
])

const query = (overrides = {}) => {
    const params = { ...props.filters, ...overrides }
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''))
}

// A new filter starts at its first page.
const filterBy = access => router.get(editorRoute('readers.directory'), query({ access, page: undefined }), { preserveScroll: true })

// When the owner asks — Enter or the button — from the first page; the state is kept so the box keeps its focus.
const searchTerm = ref(props.filters.search ?? '')

const searchFor = term => {
    if (term === (props.filters.search ?? '')) return

    router.get(editorRoute('readers.directory'), query({ search: term, page: undefined }), { preserveState: true, preserveScroll: true })
}

const goToPage = page => router.get(editorRoute('readers.directory'), query({ page }), { preserveState: true, preserveScroll: true })

const filtered = computed(() => Boolean(props.filters.search || props.filters.access))
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.reader_directory.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.reader_directory.title') }]" />

        <PageHeader :title="$t('arcadia.editor.reader_directory.title')" :description="$t('arcadia.editor.reader_directory.description')" :count="totals.readers" />

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div role="group" :aria-label="$t('arcadia.editor.reader_directory.access.label')"
                 class="inline-flex max-w-full flex-wrap gap-1 rounded-lg bg-well p-1">
                <button v-for="tab in accessTabs" :key="tab.key" type="button"
                        :aria-pressed="filters.access === tab.value"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border-0 bg-transparent px-3.5 py-1.5 text-sm font-medium text-ink-3 transition-[color,box-shadow] hover:text-ink aria-pressed:bg-raised-solid aria-pressed:text-ink aria-pressed:shadow-[var(--shadow-sm)]"
                        @click="filterBy(tab.value)">
                    <component :is="tab.icon" :size="16" aria-hidden="true" />
                    {{ $t(`arcadia.editor.reader_directory.access.${tab.key}`) }}
                    <Badge variant="neutral" class="px-1.5 py-0 font-mono text-[10px]">{{ tab.count }}</Badge>
                </button>
            </div>
            <SearchField v-model="searchTerm" class="min-w-0 flex-1 basis-64 sm:max-w-md"
                         :label="$t('arcadia.editor.reader_directory.search')" :button-label="$t('arcadia.editor.reader_directory.search_submit')"
                         @search="searchFor" />
        </div>
        <p v-if="filters.access === 'pending'" class="mt-0 mb-4 max-w-prose text-sm text-ink-3">{{ $t('arcadia.editor.reader_directory.access.pending_hint') }}</p>

        <DataTable :columns="columns" :rows="readers.data" row-key="email"
                   :empty-title="filtered ? $t('arcadia.editor.reader_directory.empty.search') : $t('arcadia.editor.reader_directory.empty.title')"
                   :empty-hint="filtered ? '' : $t('arcadia.editor.reader_directory.empty.hint')">
            <template #row="{ row }">
                <td class="px-4 py-3 align-top">
                    <button type="button" class="m-0 cursor-pointer border-0 bg-transparent p-0 text-left" @click="showing = row">
                        <span class="block text-sm font-medium text-ink hover:text-accent">{{ row.name || row.email }}</span>
                        <span v-if="row.name" class="block text-xs text-ink-4">{{ row.email }}</span>
                    </button>
                </td>
                <td class="px-4 py-3 align-top">
                    <Badge :variant="row.has_account ? 'ok' : 'pending'">
                        <component :is="row.has_account ? UserRound : Clock" :size="12" aria-hidden="true" />
                        {{ $t(`arcadia.editor.reader_directory.account.${row.has_account ? 'yes' : 'no'}`) }}
                    </Badge>
                </td>
                <td class="px-4 py-3 align-top text-right font-mono text-sm tabular-nums text-ink">{{ row.guides_count }}</td>
                <td class="px-4 py-3 align-top whitespace-nowrap">
                    <Button type="button" variant="outline" size="sm" @click="showing = row">
                        <Eye :size="14" aria-hidden="true" />
                        {{ $t('arcadia.editor.reader_directory.show') }}
                    </Button>
                </td>
            </template>

            <template #footer>
                <PaginationControls v-if="readers.last_page > 1" :page="readers.current_page" :last-page="readers.last_page" @change="goToPage" />
            </template>
        </DataTable>

        <ReaderGuidesDialog :reader="showing" :statuses="statuses" @close="showing = null" />
    </div>
</template>
