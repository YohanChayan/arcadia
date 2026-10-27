<script setup>
/**
 * The activities form of "Importar respuesta" (`ImportAnswerDialog`): the AI's answer to an activity prompt, uploaded as its `.json` file — the
 * server only reads it — checked against this draft's keys with nothing written, every problem at
 * its place, or a summary of what it attaches and what of its family it replaces; then, on
 * confirmation, attached. The draft's content itself is never changed. A file that looks like a
 * guide-content answer is said to be (`kind`), for the dialog to offer the other form.
 */
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { CircleCheck, FileJson, FileUp, TriangleAlert } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useEditorRoute } from '@/composables/useEditorRoute.js'
import { sniffAnswerKind } from './answerKind.js'

const props = defineProps({
    guideId: { type: Number, required: true },
})

const emit = defineEmits(['done', 'kind'])

const editorRoute = useEditorRoute()

const file = ref(null)
const checking = ref(false)
const importing = ref(false)
// The server's verdict on the chosen file: { problems, summary } or null.
const preview = ref(null)
const failed = ref('')
const fileError = ref('')

// A changed file has not been checked yet — and may be the other kind of answer.
watch(file, async chosen => {
    preview.value = null
    fileError.value = ''
    emit('kind', chosen ? await sniffAnswerKind(chosen) : null)
})

const fileInput = ref(null)
const pick = event => {
    file.value = event.target.files?.[0] ?? file.value
    event.target.value = ''
}

const dragging = ref(false)
const drop = event => {
    dragging.value = false
    file.value = event.dataTransfer?.files?.[0] ?? file.value
}

const check = async () => {
    checking.value = true
    failed.value = ''
    fileError.value = ''
    const form = new FormData()
    form.append('file', file.value)
    try {
        const { data } = await window.axios.post(editorRoute('activities.import.preview', props.guideId), form)
        preview.value = data
    } catch (error) {
        fileError.value = error.response?.data?.errors?.file?.[0] ?? ''
        failed.value = fileError.value ? '' : 'check'
    } finally {
        checking.value = false
    }
}

const confirm = () => {
    importing.value = true
    router.post(editorRoute('activities.import.store', props.guideId), { file: file.value }, {
        preserveScroll: true,
        onSuccess: () => emit('done'),
        onError: errors => {
            fileError.value = errors.file ?? ''
            failed.value = fileError.value ? '' : 'import'
        },
        onFinish: () => { importing.value = false },
    })
}
</script>

<template>
    <div class="flex flex-col gap-4">
            <p class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.activity_import.lead') }}</p>

            <div>
                <input ref="fileInput" type="file" accept=".json,application/json" class="hidden" @change="pick">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-control border border-dashed p-4 transition-colors"
                     :class="dragging ? 'border-accent bg-accent/10' : 'border-rule'"
                     @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="drop">
                    <p v-if="file" class="m-0 flex min-w-0 items-center gap-1.5 text-sm text-ink-2">
                        <FileJson :size="16" class="shrink-0 text-ink-3" aria-hidden="true" />
                        <span class="truncate font-mono text-xs">{{ file.name }}</span>
                    </p>
                    <p v-else class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.activity_import.drop') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" @click="fileInput?.click()">
                            <FileUp :size="14" aria-hidden="true" />
                            {{ file ? $t('arcadia.editor.activity_import.change_file') : $t('arcadia.editor.activity_import.file') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" :disabled="!file || checking" @click="check">
                            <Spinner v-if="checking" class="size-3.5" />
                            {{ $t('arcadia.editor.activity_import.check') }}
                        </Button>
                    </div>
                </div>
            </div>

            <p v-if="fileError" class="m-0 text-sm text-danger">{{ fileError }}</p>
            <p v-if="failed === 'check'" class="m-0 text-sm text-danger">{{ $t('arcadia.editor.activity_import.errors.check_failed') }}</p>

            <!-- Problems: each at its place, to fix in the answer or to ask the AI again. -->
            <div v-if="preview?.problems?.length" class="rounded-control border border-danger/40 bg-danger/5 p-3">
                <p class="m-0 flex items-center gap-1.5 text-sm font-semibold text-danger">
                    <TriangleAlert :size="15" aria-hidden="true" />
                    {{ $tChoice('arcadia.editor.activity_import.problems', preview.problems.length, { count: preview.problems.length }) }}
                </p>
                <ul class="mt-2 mb-0 max-h-56 space-y-1 overflow-y-auto pl-5 text-sm text-ink-2">
                    <li v-for="(problem, index) in preview.problems" :key="index">
                        <span class="font-mono text-xs text-ink-3">{{ problem.place }}</span> — {{ problem.message }}
                    </li>
                </ul>
            </div>

            <!-- What will be attached, and what of its family it replaces. -->
            <div v-else-if="preview?.summary" class="rounded-control border border-success/40 bg-success/5 p-3 text-sm text-ink-2">
                <p class="m-0 flex items-center gap-1.5 font-semibold text-success">
                    <CircleCheck :size="15" aria-hidden="true" />
                    {{ $t('arcadia.editor.activity_import.ready') }}
                </p>
                <p class="mt-2 mb-0">
                    {{ $t('arcadia.editor.activity_import.counts', { family: preview.summary.label, entries: preview.summary.entries, nodes: preview.summary.nodes }) }}
                </p>
                <p class="mt-1 mb-0 text-xs" :class="preview.summary.replaces ? 'text-warning' : 'text-ink-3'">
                    {{ $tChoice('arcadia.editor.activity_import.replaces', preview.summary.replaces, { family: preview.summary.label, count: preview.summary.replaces }) }}
                </p>
            </div>

            <p v-if="failed === 'import'" class="m-0 text-sm text-danger">{{ $t('arcadia.editor.activity_import.errors.import_failed') }}</p>

            <div class="flex justify-end">
                <Button type="button" variant="brand" :disabled="!preview?.summary || importing" @click="confirm">
                    <Spinner v-if="importing" class="size-3.5" />
                    {{ $t('arcadia.editor.activity_import.confirm') }}
                </Button>
            </div>
    </div>
</template>
