<script setup>
/**
 * A guide's readers, by Google email. Adding one is what lets that account open the guide once
 * it signs in. Each row carries the reader's own colours and who last saved them — "Me", "Reader"
 * or nothing — and, once they have an account, their devices and device limit (`DevicesDialog`).
 * Under each email, whether they were told by email that the guide is theirs, with a resend; the
 * add form decides whether a new reader is told (`TellReaders`). Each reader may have a name —
 * what the guide's greeting and that email call them — set when adding and changed in the row.
 *
 * Above the list, enrollment requests waiting for an answer (ADR 0022) take the full width, each
 * with its message, approved once the owner has arranged payment outside Arcadia, or declined.
 * Below them the add form, and beside it the two guide-wide settings: enrollment — whether
 * accounts the guide is not shared with may ask to enroll from its link, with the answered
 * requests and the owner's own note, his record of the payment — and the default colours new
 * readers start from.
 */
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { Check, Eye, Laptop, Mail, Palette, Pencil, Send, Trash2, UserPlus, X } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import DataTable from '@/components/common/DataTable.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import AppearanceDialog from '@/components/editor/AppearanceDialog.vue'
import DevicesDialog from '@/components/editor/DevicesDialog.vue'
import RequestNote from '@/components/editor/RequestNote.vue'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    readers: { type: Array, required: true },
    appearanceOptions: { type: Object, required: true },
    // [{ id, name, email, status, message, owner_note, created_at, decided_at }] — enrollment
    // requests, the pending first.
    accessRequests: { type: Array, default: () => [] },
    // Whether accounts it is not shared with may ask to enroll from its link.
    openForEnrollment: { type: Boolean, default: false },
    noteMax: { type: Number, default: 1000 },
    // The site's device limit; a reader's own `device_limit` overrides it.
    deviceLimit: { type: Number, required: true },
    // Whether a reader can open the guide now: emails go out only then.
    readable: { type: Boolean, default: false },
    // Whether the guide has a released version — the one progress is counted against.
    released: { type: Boolean, default: false },
})

const editorRoute = useEditorRoute()

const percent = ({ done, total }) => (total ? Math.round((done / total) * 100) : 0)

const columns = computed(() => [
    { key: 'email', label: trans('arcadia.editor.readers.columns.email') },
    { key: 'account', label: trans('arcadia.editor.readers.columns.account') },
    { key: 'progress', label: trans('arcadia.editor.readers.columns.progress') },
    { key: 'colours', label: trans('arcadia.editor.readers.columns.colours') },
    { key: 'devices', label: trans('arcadia.editor.readers.columns.devices') },
    { key: 'added', label: trans('arcadia.editor.readers.columns.added'), align: 'right' },
    { key: 'actions', label: '', align: 'right', width: '1%' },
])

// The swatch of a saved pairing, from the options the picker offers.
const swatch = (palette, accent) => ({
    colours: props.appearanceOptions.palettes.find(option => option.value === palette)?.colours,
    accent: props.appearanceOptions.accents.find(option => option.value === accent)?.hex,
    label: [
        props.appearanceOptions.palettes.find(option => option.value === palette)?.label,
        props.appearanceOptions.accents.find(option => option.value === accent)?.label,
    ].join(' · '),
})

// What the colours dialog is painting: { reader } for a row, { reader: null } for the default.
// A row is read back from the list by id, so it carries its badge as just saved.
const paintingId = ref(undefined)
const painting = computed(() => paintingId.value === undefined
    ? null
    : { reader: paintingId.value === null ? null : props.readers.find(row => row.id === paintingId.value) ?? null })

// Whose devices are open, by id, read back from the list so a revoke shows at once.
const devicesOfId = ref(null)
const devicesOf = computed(() => props.readers.find(row => row.id === devicesOfId.value) ?? null)

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

const form = useForm({ email: '', name: '', notify: true })

const add = () => form.post(editorRoute('readers.store', { guide: props.guide.id }), {
    preserveScroll: true,
    // Keeps the box as the owner left it for the next one.
    onSuccess: () => form.reset('email', 'name'),
})

// The row whose name is being changed, and the name so far.
const namingId = ref(null)
const naming = ref('')

const startNaming = row => {
    namingId.value = row.id
    naming.value = row.name ?? ''
}

const saveName = row => router.put(editorRoute('readers.rename', { guide: props.guide.id, reader: row.id }), { name: naming.value }, {
    preserveScroll: true,
    onSuccess: () => { namingId.value = null },
})

const resend = row => router.post(editorRoute('readers.notify', { guide: props.guide.id, reader: row.id }), {}, { preserveScroll: true })

const pendingRequests = computed(() => props.accessRequests.filter(request => request.status === 'pending'))
const answeredRequests = computed(() => props.accessRequests.filter(request => request.status !== 'pending'))

const setEnrollment = open => router.put(editorRoute('enrollment.update', { guide: props.guide.id }), { open }, { preserveScroll: true })

const accept = request => router.post(editorRoute('access-requests.accept', { guide: props.guide.id, accessRequest: request.id }), {}, { preserveScroll: true })
const dismiss = request => router.delete(editorRoute('access-requests.dismiss', { guide: props.guide.id, accessRequest: request.id }), { preserveScroll: true })

const pendingRemove = ref(null)
const removing = ref(false)

const confirmRemove = () => {
    removing.value = true
    router.delete(editorRoute('readers.destroy', { guide: props.guide.id, reader: pendingRemove.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            removing.value = false
            pendingRemove.value = null
        },
    })
}
</script>

<template>
    <EditorTitle :parts="[guide.title, $t('arcadia.editor.readers.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: guide.title, route: 'content.show', params: { guide: guide.id } }, { label: $t('arcadia.editor.readers.title') }]" />

        <PageHeader :title="$t('arcadia.editor.readers.title')" :description="guide.title" :count="readers.length" />

        <p class="mt-0 mb-6 max-w-2xl text-sm text-ink-3">{{ $t('arcadia.editor.readers.lead') }}</p>

        <!-- Requests waiting for an answer: the one thing here that needs the owner. -->
        <section v-if="pendingRequests.length" class="panel mb-6 border-signal/30 p-6">
            <h2 class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.readers.requests.title') }} · {{ pendingRequests.length }}</h2>
            <p class="mt-1 mb-4 text-sm text-ink-3">{{ $t('arcadia.editor.readers.requests.hint') }}</p>
            <ul class="m-0 grid list-none gap-4 p-0 lg:grid-cols-2">
                <li v-for="request in pendingRequests" :key="request.id" class="min-w-0 rounded-control border border-rule p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <a :href="`mailto:${request.email}`" class="block truncate text-sm font-medium text-ink no-underline hover:text-accent">{{ request.email }}</a>
                            <p class="m-0 truncate text-xs text-ink-4">{{ request.name }} · {{ formatDate(request.created_at) }}</p>
                        </div>
                        <Button type="button" variant="brand" size="sm" @click="accept(request)">
                            <Check :size="14" aria-hidden="true" />
                            {{ $t('arcadia.editor.readers.requests.accept') }}
                        </Button>
                        <Button type="button" variant="ghost" size="sm" @click="dismiss(request)">
                            <X :size="14" aria-hidden="true" />
                            {{ $t('arcadia.editor.readers.requests.dismiss') }}
                        </Button>
                    </div>
                    <p v-if="request.message" class="mt-2 mb-0 rounded-control bg-well px-3 py-2 text-sm whitespace-pre-line text-ink-2">{{ request.message }}</p>
                    <div class="mt-2">
                        <RequestNote :note="request.owner_note" :max="noteMax"
                                     :save-url="editorRoute('access-requests.note', { guide: guide.id, accessRequest: request.id })" />
                    </div>
                </li>
            </ul>
        </section>

        <div class="mb-6 grid items-start gap-6 lg:grid-cols-3">
            <form class="panel p-6 lg:col-span-2" @submit.prevent="add">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                    <Field :data-invalid="form.errors.email ? true : undefined">
                        <FieldLabel for="reader-email"><Eyebrow as="span">{{ $t('arcadia.editor.readers.email') }}</Eyebrow></FieldLabel>
                        <Input id="reader-email" v-model="form.email" type="email" inputmode="email" autocomplete="off"
                               :aria-invalid="form.errors.email ? 'true' : undefined" />
                        <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
                        <FieldDescription v-else>{{ $t('arcadia.editor.readers.email_hint') }}</FieldDescription>
                    </Field>
                    <Field :data-invalid="form.errors.name ? true : undefined">
                        <FieldLabel for="reader-name">
                            <Eyebrow as="span">{{ $t('arcadia.editor.readers.name') }}</Eyebrow>
                            <span class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                        </FieldLabel>
                        <Input id="reader-name" v-model="form.name" maxlength="80" autocomplete="off" :placeholder="$t('arcadia.editor.readers.name_placeholder')" />
                        <FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError>
                        <FieldDescription v-else>{{ $t('arcadia.editor.readers.name_hint') }}</FieldDescription>
                    </Field>
                </div>
                <div class="mt-5 flex flex-wrap items-end justify-between gap-4 border-t border-rule pt-4">
                    <div class="flex min-w-0 flex-1 items-start gap-2.5">
                        <Checkbox id="reader-notify" v-model="form.notify" class="mt-0.5" />
                        <div>
                            <Label for="reader-notify" class="cursor-pointer font-medium text-ink">{{ $t('arcadia.editor.readers.notify') }}</Label>
                            <p class="mt-0.5 mb-0 text-xs text-ink-3">{{ $t('arcadia.editor.readers.notify_hint') }}</p>
                        </div>
                    </div>
                    <Button type="submit" variant="brand" :disabled="form.processing || !form.email">
                        <UserPlus :size="16" aria-hidden="true" />
                        {{ $t('arcadia.editor.readers.add') }}
                    </Button>
                </div>
            </form>

            <div class="flex flex-col gap-6">
                <section class="panel p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.readers.enrollment.title') }}</h2>
                            <p class="mt-1 mb-0 text-sm text-ink-3">
                                {{ $t(openForEnrollment ? 'arcadia.editor.readers.enrollment.open_hint' : 'arcadia.editor.readers.enrollment.closed_hint') }}
                            </p>
                        </div>
                        <Switch :model-value="openForEnrollment" :aria-label="$t('arcadia.editor.readers.enrollment.switch')"
                                @update:model-value="setEnrollment" />
                    </div>

                    <details v-if="answeredRequests.length" class="mt-4">
                        <summary class="cursor-pointer text-sm text-ink-3 hover:text-ink">{{ $t('arcadia.editor.readers.requests.answered', { count: answeredRequests.length }) }}</summary>
                        <ul class="m-0 mt-3 flex list-none flex-col gap-3 p-0">
                            <li v-for="request in answeredRequests" :key="request.id">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="m-0 truncate text-sm text-ink">{{ request.email }}</p>
                                        <p class="m-0 truncate text-xs text-ink-4">{{ request.name }} · {{ formatDate(request.decided_at ?? request.created_at) }}</p>
                                    </div>
                                    <Badge :variant="request.status === 'approved' ? 'ok' : 'neutral'">{{ $t(`arcadia.editor.readers.requests.status.${request.status}`) }}</Badge>
                                </div>
                                <div class="mt-1.5">
                                    <RequestNote :note="request.owner_note" :max="noteMax"
                                                 :save-url="editorRoute('access-requests.note', { guide: guide.id, accessRequest: request.id })" />
                                </div>
                            </li>
                        </ul>
                    </details>
                </section>

                <section class="panel flex items-center gap-4 p-5">
                    <span class="flex h-10 w-14 shrink-0 overflow-hidden rounded-md border border-rule" aria-hidden="true">
                        <span class="h-full w-1/2" :style="{ background: swatch(guide.palette, guide.accent).colours?.ground }"></span>
                        <span class="flex h-full w-1/2 items-center justify-center" :style="{ background: swatch(guide.palette, guide.accent).colours?.surface }">
                            <span class="size-3 rounded-full" :style="{ background: swatch(guide.palette, guide.accent).accent }"></span>
                        </span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.appearance.default_title') }}</p>
                        <p class="mt-0.5 mb-0 text-xs text-ink-3">{{ swatch(guide.palette, guide.accent).label }} — {{ $t('arcadia.editor.appearance.default_hint') }}</p>
                    </div>
                    <Button type="button" variant="outline" size="icon" @click="paintingId = null"
                            :aria-label="$t('arcadia.editor.appearance.edit')" :title="$t('arcadia.editor.appearance.edit')">
                        <Palette :size="16" aria-hidden="true" />
                    </Button>
                </section>
            </div>
        </div>

        <DataTable :columns="columns" :rows="readers"
                   :empty-title="$t('arcadia.editor.readers.empty.title')"
                   :empty-hint="$t('arcadia.editor.readers.empty.hint')">
            <template #row="{ row }">
                <td class="px-4 py-3 align-top">
                    <!-- What the guide calls them: their own name here, else their account's first name. -->
                    <form v-if="namingId === row.id" class="mb-1 flex items-center gap-1.5" @submit.prevent="saveName(row)">
                        <Input v-model="naming" maxlength="80" class="h-8 w-40" :placeholder="$t('arcadia.editor.readers.name_placeholder')"
                               :aria-label="$t('arcadia.editor.readers.name')" @keydown.esc="namingId = null" />
                        <Button type="submit" size="icon-sm" variant="ghost" :aria-label="$t('arcadia.editor.readers.rename_save')"><Check :size="14" aria-hidden="true" /></Button>
                        <Button type="button" size="icon-sm" variant="ghost" :aria-label="$t('arcadia.editor.cancel')" @click="namingId = null"><X :size="14" aria-hidden="true" /></Button>
                    </form>
                    <p v-else class="m-0 mb-0.5 flex items-center gap-1.5 text-sm">
                        <span v-if="row.display_name" class="font-semibold text-accent">{{ row.display_name }}</span>
                        <span v-if="row.display_name && !row.name" class="text-xs text-ink-4">{{ $t('arcadia.editor.readers.name_from_account') }}</span>
                        <span v-if="!row.display_name" class="text-xs text-ink-4">{{ $t('arcadia.editor.readers.no_name') }}</span>
                        <button type="button" class="rounded p-0.5 text-ink-4 hover:text-accent" :aria-label="`${$t('arcadia.editor.readers.rename')}: ${row.email}`" @click="startNaming(row)">
                            <Pencil :size="12" aria-hidden="true" />
                        </button>
                    </p>
                    <p class="m-0 font-medium break-all text-ink">{{ row.email }}</p>
                    <!-- Told by email, waiting for the guide to open, or not told — and a (re)send once it can be opened. -->
                    <p class="mt-1 mb-0 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-4">
                        <span class="inline-flex items-center gap-1">
                            <Mail :size="12" aria-hidden="true" />
                            <template v-if="row.notified_at">{{ $t('arcadia.editor.readers.told.sent', { date: formatDate(row.notified_at) }) }}</template>
                            <template v-else-if="row.notify">{{ $t('arcadia.editor.readers.told.waiting') }}</template>
                            <template v-else>{{ $t('arcadia.editor.readers.told.off') }}</template>
                        </span>
                        <button v-if="readable" type="button" class="inline-flex items-center gap-1 font-medium text-accent hover:underline" @click="resend(row)">
                            <Send :size="11" aria-hidden="true" />
                            {{ $t(row.notified_at ? 'arcadia.editor.readers.told.resend' : 'arcadia.editor.readers.told.send') }}
                        </button>
                    </p>
                </td>
                <td class="px-4 py-3 align-top">
                    <Badge :variant="row.has_account ? 'ok' : 'neutral'">
                        {{ $t(row.has_account ? 'arcadia.editor.readers.account.yes' : 'arcadia.editor.readers.account.no') }}
                    </Badge>
                </td>
                <!-- Where they stand on the released version: lines ticked, stages finished, last tick. -->
                <td class="px-4 py-3 align-top">
                    <div v-if="row.progress" class="w-36">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="font-mono tabular-nums text-ink-2">{{ row.progress.done }}/{{ row.progress.total }}</span>
                            <span class="font-mono text-xs tabular-nums text-accent">{{ percent(row.progress) }}%</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-well" role="progressbar"
                             :aria-valuenow="percent(row.progress)" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-accent" :style="{ width: `${percent(row.progress)}%` }"></div>
                        </div>
                        <p class="mt-1 mb-0 text-xs text-ink-4">
                            {{ $t('arcadia.editor.readers.progress.stages', { done: row.progress.stages.done, total: row.progress.stages.total }) }}
                        </p>
                        <p class="m-0 text-xs text-ink-4">
                            {{ row.progress.last_ticked_at
                                ? $t('arcadia.editor.readers.progress.last', { date: formatDate(row.progress.last_ticked_at) })
                                : $t('arcadia.editor.readers.progress.none') }}
                        </p>
                    </div>
                    <span v-else class="text-sm text-ink-4" :title="released ? undefined : $t('arcadia.editor.readers.progress.no_release')">—</span>
                </td>
                <td class="px-4 py-3 align-top">
                    <button type="button" class="flex items-center gap-2 rounded-control text-left text-sm text-ink-2 hover:text-accent"
                            :aria-label="`${$t('arcadia.editor.appearance.edit')}: ${row.email}`" @click="paintingId = row.id">
                        <span class="flex h-6 w-9 shrink-0 overflow-hidden rounded border border-rule" aria-hidden="true">
                            <span class="h-full w-1/2" :style="{ background: swatch(row.palette, row.accent).colours?.ground }"></span>
                            <span class="flex h-full w-1/2 items-center justify-center" :style="{ background: swatch(row.palette, row.accent).colours?.surface }">
                                <span class="size-2 rounded-full" :style="{ background: swatch(row.palette, row.accent).accent }"></span>
                            </span>
                        </span>
                        <span class="whitespace-nowrap">{{ swatch(row.palette, row.accent).label }}</span>
                    </button>
                    <Badge v-if="row.appearance_by" :variant="row.appearance_by === 'reader' ? 'pending' : 'active'" class="mt-1.5"
                           :title="row.appearance_at ? formatDate(row.appearance_at) : undefined">
                        {{ $t(`arcadia.editor.appearance.by.${row.appearance_by}`) }}
                    </Badge>
                </td>
                <td class="px-4 py-3 align-top">
                    <button v-if="row.user_id" type="button"
                            class="inline-flex items-center gap-1.5 rounded-control text-sm whitespace-nowrap text-ink-2 hover:text-accent"
                            :aria-label="`${$t('arcadia.editor.devices.title')}: ${row.email}`" @click="devicesOfId = row.id">
                        <Laptop :size="14" aria-hidden="true" />
                        {{ $t('arcadia.editor.devices.count', { used: row.devices.length, limit: row.device_limit ?? deviceLimit }) }}
                    </button>
                    <span v-else class="text-sm text-ink-4">—</span>
                </td>
                <td class="px-4 py-3 align-top text-right font-mono text-xs text-ink-4">{{ formatDate(row.created_at) }}</td>
                <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                    <!-- The guide as they see it, their ticks included: read only, in a tab of its own. -->
                    <a v-if="row.user_id && released" :href="editorRoute('readers.view', { guide: guide.id, reader: row.id })" target="_blank" rel="noopener"
                       :class="buttonVariants({ variant: 'ghost', size: 'icon-sm' })"
                       :aria-label="`${$t('arcadia.editor.readers.view.action')}: ${row.email}`" :title="$t('arcadia.editor.readers.view.action')">
                        <Eye :size="14" aria-hidden="true" />
                    </a>
                    <Button type="button" variant="ghost-danger" size="icon-sm" @click="pendingRemove = row"
                            :aria-label="$t('arcadia.editor.readers.remove.action')" :title="$t('arcadia.editor.readers.remove.action')">
                        <Trash2 :size="14" aria-hidden="true" />
                    </Button>
                </td>
            </template>
        </DataTable>

        <AppearanceDialog :guide="guide" :options="appearanceOptions" :target="painting" @close="paintingId = undefined" />

        <DevicesDialog :reader="devicesOf" :site-limit="deviceLimit" @close="devicesOfId = null" />

        <ConfirmDialog :open="pendingRemove !== null"
                       :title="$t('arcadia.editor.readers.remove.title')"
                       :body="$t('arcadia.editor.readers.remove.body')"
                       :confirm-label="$t('arcadia.editor.readers.remove.confirm')"
                       :processing="removing"
                       @confirm="confirmRemove"
                       @cancel="pendingRemove = null" />
    </div>
</template>
