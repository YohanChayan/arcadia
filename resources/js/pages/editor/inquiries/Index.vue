<script setup>
/**
 * What visitors asked for on this language's landing page, ten to a page, searchable and filtered by
 * where each stands — nothing done yet, or a guide started — and by the kind of contact. Each row
 * offers its next step as its one button ("Crear guía", "Abrir guía"); answering and deleting sit
 * in its menu. The research prompt is prepared on the guide's content screen, not here.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { BookOpen, BookPlus, Mail, MessageCircle, MoreHorizontal, Plus, Trash2, UserRound } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import DataTable from '@/components/common/DataTable.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import CopyButton from '@/components/common/CopyButton.vue'
import SearchField from '@/components/common/SearchField.vue'
import CreateInquiryDialog from '@/components/editor/CreateInquiryDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    inquiries: { type: Object, required: true },
    purposes: { type: Array, required: true },
    stages: { type: Array, required: true },
    // What the list is filtered by now: { stage, kind, search }, each null for none.
    filters: { type: Object, required: true },
    stageCounts: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const columns = computed(() => [
    { key: 'topic', label: trans('arcadia.editor.inquiries.columns.topic') },
    { key: 'contact', label: trans('arcadia.editor.inquiries.columns.contact') },
    { key: 'status', label: trans('arcadia.editor.inquiries.columns.status') },
    { key: 'when', label: trans('arcadia.editor.inquiries.columns.when'), align: 'right' },
    { key: 'actions', label: '', align: 'right', width: '1%' },
])

const purposeFor = value => props.purposes.find(p => p.value === value)?.label ?? null
const stageFor = value => props.stages.find(s => s.value === value) ?? { label: value, tone: 'neutral' }

const formatDate = iso => new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })

const pendingDelete = ref(null)
const deleting = ref(false)

const confirmDelete = () => {
    deleting.value = true
    router.delete(editorRoute('inquiries.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}

// Starts the draft guide answering a request and opens its content, where its prompt is prepared.
const startingGuide = ref(null)
const startGuide = row => {
    startingGuide.value = row.id
    router.post(editorRoute('inquiries.guide', row.id), {}, { onFinish: () => { startingGuide.value = null } })
}

const creating = ref(false)

// Each stage with how many it would show, whatever stage is filtered now.
const stageFilters = computed(() => [
    { value: null, label: trans('arcadia.editor.inquiries.filters.all'), count: Object.values(props.stageCounts).reduce((sum, n) => sum + n, 0) },
    ...props.stages.map(s => ({ value: s.value, label: s.label, count: props.stageCounts[s.value] ?? 0 })),
])

const query = (overrides = {}) => {
    const params = { ...props.filters, ...overrides }
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''))
}

// A new filter starts at its first page.
const filterBy = overrides => router.get(editorRoute('inquiries.index'), query({ ...overrides, page: undefined }), { preserveScroll: true })

// reka's Select cannot hold null, so "any contact" travels as `all`.
const kind = computed({
    get: () => props.filters.kind ?? 'all',
    set: value => filterBy({ kind: value === 'all' ? null : value }),
})

// When the owner asks — Enter or the button — from the first page; the state is kept so the box keeps its focus.
const searchTerm = ref(props.filters.search ?? '')

const search = term => {
    if (term === (props.filters.search ?? '')) return

    router.get(editorRoute('inquiries.index'), query({ search: term, page: undefined }), { preserveState: true, preserveScroll: true })
}

const filtered = computed(() => Boolean(props.filters.stage || props.filters.kind || props.filters.search))

const clearFilters = () => {
    searchTerm.value = ''
    router.get(editorRoute('inquiries.index'), {}, { preserveScroll: true })
}

const goToPage = page => router.get(editorRoute('inquiries.index'), query({ page }), { preserveState: true, preserveScroll: true })
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.inquiries.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.inquiries.title') }]" />

        <PageHeader :title="$t('arcadia.editor.inquiries.title')" :description="$t('arcadia.editor.inquiries.description')" :count="inquiries.total">
            <template #actions>
                <Button type="button" variant="brand" size="sm" @click="creating = true">
                    <Plus :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.inquiries.create.action') }}
                </Button>
            </template>
        </PageHeader>

        <CreateInquiryDialog v-model:open="creating" />

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <SearchField v-model="searchTerm" class="min-w-0 flex-1 basis-64 sm:max-w-md"
                         :label="$t('arcadia.editor.inquiries.search')" :button-label="$t('arcadia.editor.inquiries.search_submit')"
                         @search="search" />
            <Select v-model="kind">
                <SelectTrigger class="w-auto min-w-44" :aria-label="$t('arcadia.editor.inquiries.filters.kind')"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in ['all', 'whatsapp', 'email']" :key="option" :value="option">
                        {{ $t(`arcadia.editor.inquiries.filters.kinds.${option}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button v-if="filtered" type="button" variant="ghost" size="sm" @click="clearFilters">
                {{ $t('arcadia.editor.inquiries.filters.clear') }}
            </Button>
        </div>

        <div role="group" :aria-label="$t('arcadia.editor.inquiries.filters.label')" class="mb-4 flex flex-wrap gap-1">
            <Button v-for="filter in stageFilters" :key="filter.value ?? 'all'" type="button" size="sm"
                    :variant="filters.stage === filter.value ? 'secondary' : 'ghost'"
                    :aria-pressed="filters.stage === filter.value"
                    @click="filterBy({ stage: filter.value })">
                {{ filter.label }}
                <span class="font-mono text-xs tabular-nums text-ink-4">{{ filter.count }}</span>
            </Button>
        </div>

        <DataTable :columns="columns" :rows="inquiries.data"
                   :empty-title="filtered ? $t('arcadia.editor.inquiries.filters.empty') : $t('arcadia.editor.inquiries.empty.title')"
                   :empty-hint="filtered ? '' : $t('arcadia.editor.inquiries.empty.hint')">
            <template #row="{ row }">
                <td class="px-4 py-3 align-top">
                    <p class="m-0 text-ink" :class="row.stage === 'pending' ? 'font-semibold' : ''">{{ row.topic }}</p>
                    <p v-if="row.purpose || row.name" class="mt-0.5 mb-0 text-xs text-ink-4">
                        {{ [row.name, purposeFor(row.purpose)].filter(Boolean).join(' · ') }}
                    </p>
                    <!-- The account that asked: the email to assign the guide to. -->
                    <p v-if="row.account_email" class="mt-0.5 mb-0 inline-flex items-center gap-1 text-xs text-ink-3"
                       :title="$t('arcadia.editor.inquiries.account')">
                        <UserRound :size="12" aria-hidden="true" />
                        {{ row.account_email }}
                    </p>
                    <p v-if="row.guide" class="mt-1 mb-0">
                        <Link :href="editorRoute('content.show', { guide: row.guide.id })" class="inline-flex items-center gap-1 text-xs text-success no-underline hover:underline">
                            <BookOpen :size="12" aria-hidden="true" />
                            {{ $t('arcadia.editor.prompts.provenance.guide_badge', { title: row.guide.title }) }}
                        </Link>
                    </p>
                </td>
                <td class="px-4 py-3 align-top whitespace-nowrap">
                    <div class="flex items-center gap-1">
                        <a :href="row.reply_url" v-bind="row.contact_kind === 'whatsapp' ? { target: '_blank', rel: 'noopener noreferrer' } : {}"
                           class="inline-flex items-center gap-1.5 text-sm font-medium text-accent no-underline hover:underline"
                           :title="$t(`arcadia.editor.inquiries.reply.${row.contact_kind}`)">
                            <component :is="row.contact_kind === 'whatsapp' ? MessageCircle : Mail" :size="14" aria-hidden="true" />
                            {{ row.contact }}
                        </a>
                        <CopyButton :value="row.contact" :label="$t('arcadia.editor.inquiries.copy')" :copied-label="$t('arcadia.editor.inquiries.copied')" />
                    </div>
                </td>
                <td class="px-4 py-3 align-top">
                    <Badge :variant="stageFor(row.stage).tone">{{ stageFor(row.stage).label }}</Badge>
                </td>
                <td class="px-4 py-3 align-top text-right font-mono text-xs whitespace-nowrap text-ink-4">{{ formatDate(row.created_at) }}</td>
                <td class="px-4 py-3 align-top whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1">
                        <!-- The next step, and only that one. -->
                        <Link v-if="row.guide" :href="editorRoute('content.show', { guide: row.guide.id })"
                              :class="buttonVariants({ variant: 'outline', size: 'sm' })" class="no-underline">
                            <BookOpen :size="14" aria-hidden="true" />
                            {{ $t('arcadia.editor.inquiries.open_guide') }}
                        </Link>
                        <Button v-else type="button" variant="outline" size="sm"
                                :disabled="startingGuide === row.id" @click="startGuide(row)">
                            <BookPlus :size="14" aria-hidden="true" />
                            {{ $t('arcadia.editor.prompts.provenance.start') }}
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.inquiries.more')" :title="$t('arcadia.editor.inquiries.more')">
                                    <MoreHorizontal :size="14" aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem as-child>
                                    <a :href="row.reply_url" v-bind="row.contact_kind === 'whatsapp' ? { target: '_blank', rel: 'noopener noreferrer' } : {}" class="no-underline">
                                        <component :is="row.contact_kind === 'whatsapp' ? MessageCircle : Mail" aria-hidden="true" />
                                        {{ $t(`arcadia.editor.inquiries.reply.${row.contact_kind}`) }}
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem variant="destructive" @select="pendingDelete = row">
                                    <Trash2 aria-hidden="true" />
                                    {{ $t('arcadia.editor.inquiries.delete.action') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </td>
            </template>

            <template #footer>
                <PaginationControls v-if="inquiries.last_page > 1" :page="inquiries.current_page" :last-page="inquiries.last_page" @change="goToPage" />
            </template>
        </DataTable>

        <ConfirmDialog :open="pendingDelete !== null"
                       :title="$t('arcadia.editor.inquiries.delete.title')"
                       :body="$t('arcadia.editor.inquiries.delete.body')"
                       :confirm-label="$t('arcadia.editor.inquiries.delete.confirm')"
                       :processing="deleting"
                       @confirm="confirmDelete"
                       @cancel="pendingDelete = null" />
    </div>
</template>
