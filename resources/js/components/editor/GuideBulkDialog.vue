<script setup>
/**
 * The confirmation for one action on many guides — or one guide's publish from its row.
 *
 * It asks the server first what the action would reach (`guides.bulk.preview`): the selection may
 * span pages this screen has not loaded. For publishing, the answer comes grouped as
 * `GuidePublishing` groups them — drafts it will release with the guide, guides it will only
 * publish, those already up to date, and those left out for having no content — so nothing is
 * released that the owner was not told about. With nothing publishable, it cannot be confirmed.
 */
import { computed, ref, watch } from 'vue'
import { router, useHttp } from '@inertiajs/vue3'
import { AlertTriangle, Send, FileX } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // `{ action, all, ids, except, status, search }`, or null while closed.
    request: { type: Object, default: null },
})

const emit = defineEmits(['close', 'done'])

const editorRoute = useEditorRoute()

// The groups in the order they are read: what changes first, what is left out last.
const PUBLISH_GROUPS = [
    { key: 'releases_draft', tone: 'border-warning/30 bg-warning/10 text-ink', icon: AlertTriangle, iconTone: 'text-warning' },
    { key: 'publishes', tone: 'border-success/25 bg-success/5 text-ink', icon: Send, iconTone: 'text-success' },
    { key: 'ready', tone: 'border-rule text-ink-3', icon: null },
    { key: 'no_content', tone: 'border-danger/25 bg-danger/5 text-ink', icon: FileX, iconTone: 'text-danger' },
]

const CONFIRM_VARIANT = { publish: 'success', draft: 'outline', delete: 'destructive' }

const preview = useHttp({ action: null, all: false, ids: [], except: [], status: null, search: null })
const applying = ref(false)

// The server's answer for the request now open; one that arrives for an earlier request is dropped.
const answer = ref(null)
const action = computed(() => props.request?.action)

watch(() => props.request, request => {
    preview.cancel()
    answer.value = null
    if (!request) return

    Object.assign(preview, request)
    preview.post(editorRoute('guides.bulk.preview'))
        .then(response => { if (props.request === request) answer.value = response })
        // A cancelled request rejects too; only a failure for the open request closes the dialog.
        .catch(() => { if (props.request === request && !answer.value) emit('close') })
})

const groups = computed(() => PUBLISH_GROUPS
    .map(group => ({ ...group, ...answer.value?.groups?.[group.key] }))
    .filter(group => group.count > 0))

const publishable = computed(() => (answer.value?.groups?.releases_draft?.count ?? 0) + (answer.value?.groups?.publishes?.count ?? 0))

const canConfirm = computed(() => {
    if (!answer.value || applying.value) return false
    if (action.value === 'publish') return publishable.value > 0
    return answer.value.total > 0
})

const confirm = () => {
    applying.value = true
    router.post(editorRoute('guides.bulk'), props.request, {
        preserveScroll: true,
        onSuccess: () => emit('done'),
        onFinish: () => { applying.value = false },
    })
}

// Escape and Cancel both arrive here as `open` becoming false; not while it is being applied.
const onOpenChange = isOpen => { if (!isOpen && !applying.value) emit('close') }
</script>

<template>
    <AlertDialog :open="request !== null" @update:open="onOpenChange">
        <AlertDialogContent class="max-w-lg">
            <template v-if="request">
                <AlertDialogTitle class="font-display text-lg font-semibold text-ink">
                    <template v-if="answer">{{ $tChoice(`arcadia.editor.guides.bulk.titles.${action}`, answer.total, { count: answer.total }) }}</template>
                    <template v-else>{{ $t('arcadia.editor.guides.bulk.checking') }}</template>
                </AlertDialogTitle>

                <AlertDialogDescription v-if="!answer" class="flex items-center gap-2 text-sm text-ink-3">
                    <Spinner /> {{ $t('arcadia.editor.guides.bulk.checking') }}
                </AlertDialogDescription>

                <AlertDialogDescription v-else-if="action !== 'publish'" class="text-sm text-ink-3">
                    {{ $t(`arcadia.editor.guides.bulk.bodies.${action}`) }}
                </AlertDialogDescription>

                <template v-else>
                    <AlertDialogDescription class="sr-only">{{ $tChoice('arcadia.editor.guides.bulk.titles.publish', answer.total, { count: answer.total }) }}</AlertDialogDescription>
                    <ul class="m-0 flex max-h-[50vh] list-none flex-col gap-2 overflow-y-auto p-0">
                        <li v-for="group in groups" :key="group.key" class="rounded-lg border p-3 text-sm" :class="group.tone">
                            <p class="m-0 flex items-start gap-2">
                                <component :is="group.icon" v-if="group.icon" :size="16" class="mt-0.5 shrink-0" :class="group.iconTone" aria-hidden="true" />
                                <span>
                                    <span class="font-mono font-semibold tabular-nums">{{ group.count }}</span>
                                    · {{ $t(`arcadia.editor.guides.bulk.groups.${group.key}`) }}
                                </span>
                            </p>
                            <ul class="m-0 mt-1.5 list-none p-0 pl-6 text-xs text-ink-3">
                                <li v-for="title in group.titles" :key="title" class="truncate">{{ title }}</li>
                                <li v-if="group.count > group.titles.length">{{ $t('arcadia.editor.guides.bulk.more', { count: group.count - group.titles.length }) }}</li>
                            </ul>
                        </li>
                    </ul>
                    <p v-if="!publishable" class="m-0 text-sm text-danger">{{ $t('arcadia.editor.guides.bulk.nothing') }}</p>
                </template>

                <AlertDialogFooter class="mt-2">
                    <AlertDialogCancel :disabled="applying">{{ $t('admin.confirm.cancel') }}</AlertDialogCancel>
                    <Button :variant="CONFIRM_VARIANT[action]" :disabled="!canConfirm" @click="confirm">
                        <Spinner v-if="applying" />
                        {{ $t(`arcadia.editor.guides.bulk.confirm.${action}`) }}
                    </Button>
                </AlertDialogFooter>
            </template>
        </AlertDialogContent>
    </AlertDialog>
</template>
