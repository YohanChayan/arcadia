<script setup>
/**
 * A guide's content tree, on its latest version — or an earlier one, read-only: the prose
 * sections, then stage → card → item. The version controls live here too: release the draft,
 * draft the next one, its change log, and fix-everywhere for a dead line.
 *
 * Everything is edited in place. The context every block needs — whether the version can be
 * edited, the kind labels, the routes, moving and deleting — is provided here once, so the
 * blocks stay small. One delete confirmation serves the whole tree.
 *
 * A release ends on the guide's link, so the owner can share what they just released without
 * going back to the list for it.
 *
 * The research prompt opens from the header, to research the guide again in whichever version of
 * it the owner picks — on the one last copied for this guide, filled as it was. Copying records it
 * on the guide (`GuidePromptController`), which freezes that version. It lived on the requests
 * screen until 2026-10-22.
 */
import { provide, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { CircleCheck, Plus, Lock, Rocket, CopyPlus, History, GitCompare, Eye, FileJson, ScrollText } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import CodeBlock from '@/components/common/CodeBlock.vue'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button, buttonVariants } from '@/components/ui/button'
import NodeForm from '@/components/editor/content/NodeForm.vue'
import SectionRow from '@/components/editor/content/SectionRow.vue'
import StageBlock from '@/components/editor/content/StageBlock.vue'
import VersionChanges from '@/components/editor/content/VersionChanges.vue'
import FixDialog from '@/components/editor/content/FixDialog.vue'
import ImportDialog from '@/components/editor/content/ImportDialog.vue'
import VersionedPromptFiller from '@/components/editor/prompts/VersionedPromptFiller.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    version: { type: Object, required: true },
    versions: { type: Array, required: true },
    changes: { type: Array, required: true },
    copies: { type: Object, required: true },
    sections: { type: Array, required: true },
    stages: { type: Array, required: true },
    kinds: { type: Object, required: true },
    linkKinds: { type: Array, required: true },
    // `4 a 8`: the minutes a step should take, for a line's replacement prompt.
    stepLength: { type: String, required: true },
    // The research prompt — { prompt: { id, name, versions } or null where it is not seeded,
    // versionId: the one last copied for this guide, values: what its blanks start filled with } —
    // and the choices of its level, language and category.
    research: { type: Object, required: true },
    choices: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const pendingDelete = ref(null)
const deleting = ref(false)

// Only the latest version, while still a draft, is edited.
const editable = props.version.latest && !props.version.released

// The AI's answer, brought into this draft.
const importing = ref(false)

const guide = props.guide.id
const route = (action, ...args) => {
    switch (action) {
        case 'fix': return editorRoute('items.fix', { guide, key: args[0] })
        case 'changes': return editorRoute('versions.changes.store', { guide })
        case 'change': return editorRoute('versions.changes.destroy', { guide, change: args[0] })
        default: {
            const [node, id = null, direction = null] = args
            return editorRoute(`content.${action}`, {
                guide,
                node,
                ...(id === null ? {} : { id }),
                ...(direction === null ? {} : { direction }),
            })
        }
    }
}

const fixing = ref(null)

provide('content', {
    editable,
    copies: props.copies,
    fix: item => (fixing.value = item),
    kinds: props.kinds,
    linkKinds: props.linkKinds,
    stepLength: props.stepLength,
    route,
    label: (list, value) => props.kinds[list].find(option => option.value === value)?.label ?? value,
    move: (node, row, direction) => router.post(route('move', node, row.id, direction), {}, { preserveScroll: true }),
    requestDelete: (node, row) => (pendingDelete.value = { node, row }),
})

const confirmDelete = () => {
    deleting.value = true
    router.delete(route('destroy', pendingDelete.value.node, pendingDelete.value.row.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}

const addingSection = ref(false)
const addingStage = ref(false)

const showVersion = number => router.get(editorRoute('content.show', { guide }), { version: number })

const releasing = ref(false)
const confirmingRelease = ref(false)
// The number just released; set, it opens the dialog with the link.
const released = ref(null)
const release = () => {
    releasing.value = true
    const number = props.version.number
    router.post(editorRoute('versions.release', { guide }), {}, {
        preserveScroll: true,
        onSuccess: () => (released.value = number),
        onFinish: () => {
            releasing.value = false
            confirmingRelease.value = false
        },
    })
}
const draftNext = () => router.post(editorRoute('versions.store', { guide }))

// The research prompt's popup, and what recording the last copy said.
const researching = ref(false)
const researchRecorded = ref(false)
const researchError = ref('')

const openResearch = () => {
    researchRecorded.value = false
    researchError.value = ''
    researching.value = true
}

const recordResearch = payload => {
    researchError.value = ''
    router.post(editorRoute('guides.prompt', { guide }), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { researchRecorded.value = true },
        onError: errors => { researchError.value = errors.values ?? Object.values(errors)[0] ?? '' },
    })
}
</script>

<template>
    <EditorTitle :parts="[guide.title, $t('arcadia.editor.content.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: guide.title }]" />

        <!-- The header carries only what moves the version on — release it, or draft the next — and
             how it reads. The rest is the toolbar below, in two groups: which version, and its research. -->
        <PageHeader :title="guide.title" :eyebrow="$t('arcadia.editor.content.title')">
            <template #actions>
                <!-- A new tab, so the owner keeps their place in the editor. -->
                <a :href="editorRoute('preview', { guide, version: version.number })" target="_blank" rel="noopener"
                   :class="buttonVariants({ variant: 'outline', size: 'sm' })" class="no-underline">
                    <Eye :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.guides.preview') }}
                </a>
                <Button v-if="editable" type="button" variant="brand" size="sm" @click="confirmingRelease = true">
                    <Rocket :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.versions.release.action') }}
                </Button>
                <Button v-if="version.latest && version.released" type="button" variant="brand" size="sm" @click="draftNext" :title="$t('arcadia.editor.versions.draft_next_hint')">
                    <CopyPlus :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.versions.draft_next') }}
                </Button>
            </template>
        </PageHeader>

        <div class="panel mb-6 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3">
            <div role="group" :aria-label="$t('arcadia.editor.content.toolbar.versions')" class="flex flex-wrap items-center gap-2">
                <Eyebrow as="span" class="mr-1 hidden sm:inline">{{ $t('arcadia.editor.content.toolbar.versions') }}</Eyebrow>
                <Select :model-value="String(version.number)" @update:model-value="number => showVersion(number)">
                    <SelectTrigger size="sm" class="h-8 w-auto gap-2 data-[size=sm]:py-0" :aria-label="$t('arcadia.editor.versions.history')">
                        <History :size="14" aria-hidden="true" />
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="v in versions" :key="v.id" :value="String(v.number)">
                            {{ $t('arcadia.editor.content.version', { number: v.number }) }} ·
                            {{ v.released_at ? $t('arcadia.editor.content.released') : $t('arcadia.editor.content.draft') }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Link v-if="versions.length > 1" :href="editorRoute('compare', { guide })" :class="buttonVariants({ variant: 'ghost', size: 'sm' })" class="no-underline">
                    <GitCompare :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.compare.action') }}
                </Link>
            </div>

            <!-- In the order the work goes: copy the prompt, then bring the AI's answer in. -->
            <div role="group" :aria-label="$t('arcadia.editor.content.toolbar.research')" class="flex flex-wrap items-center gap-2">
                <Eyebrow as="span" class="mr-1 hidden sm:inline">{{ $t('arcadia.editor.content.toolbar.research') }}</Eyebrow>
                <Button type="button" variant="outline" size="sm" @click="openResearch">
                    <ScrollText :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.content.research.action') }}
                </Button>
                <Button v-if="editable" type="button" variant="outline" size="sm" @click="importing = true">
                    <FileJson :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.import.action') }}
                </Button>
            </div>
        </div>

        <ImportDialog v-if="editable" v-model:open="importing" :guide-id="guide" />

        <!-- The research prompt, filled from the guide: copy it into ChatGPT or Gemini, then import the answer. -->
        <Dialog v-model:open="researching">
            <DialogContent class="w-[calc(100%-2rem)] max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('arcadia.editor.content.research.title') }}</DialogTitle>
                    <DialogDescription>{{ research.prompt ? $t('arcadia.editor.content.research.hint') : $t('arcadia.editor.content.research.missing') }}</DialogDescription>
                </DialogHeader>
                <p v-if="researchRecorded" class="m-0 flex items-center gap-1.5 text-sm text-success">
                    <CircleCheck :size="15" aria-hidden="true" />
                    {{ $t('arcadia.editor.content.research.recorded') }}
                </p>
                <p v-if="researchError" class="m-0 text-sm text-danger">{{ researchError }}</p>
                <VersionedPromptFiller v-if="research.prompt" :prompt="research.prompt" :version-id="research.versionId"
                                       :choices="choices" :initial="research.values" @copied="recordResearch" />
            </DialogContent>
        </Dialog>

        <p v-if="!version.latest" class="panel mb-6 flex flex-wrap items-center gap-2 p-4 text-sm text-ink-2">
            <History :size="16" class="shrink-0 text-accent" aria-hidden="true" />
            {{ $t('arcadia.editor.versions.viewing_old') }}
            <Link :href="editorRoute('content.show', { guide })" class="font-medium text-accent no-underline hover:underline">{{ $t('arcadia.editor.versions.latest') }}</Link>
        </p>
        <p v-else-if="version.released" class="panel mb-6 flex items-start gap-2 p-4 text-sm text-ink-2">
            <Lock :size="16" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
            {{ $t('arcadia.editor.content.released_notice') }}
        </p>

        <VersionChanges v-if="version.number > 1" :changes="changes" />

        <section class="panel mb-6 p-5 sm:p-6">
            <h2 class="m-0 font-display text-base font-semibold text-ink">{{ $t('arcadia.editor.content.sections.title') }}</h2>
            <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.editor.content.sections.hint') }}</p>

            <div class="mt-4 flex flex-col gap-4">
                <SectionRow v-for="(section, index) in sections" :key="section.id" :section="section"
                            :first="index === 0" :last="index === sections.length - 1" />
                <p v-if="sections.length === 0" class="m-0 text-sm text-ink-4">{{ $t('arcadia.editor.content.sections.empty') }}</p>
            </div>

            <template v-if="editable">
                <NodeForm v-if="addingSection" class="mt-4" node="sections" @done="addingSection = false" />
                <Button v-else type="button" variant="outline" size="sm" class="mt-4" @click="addingSection = true">
                    <Plus :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.content.sections.add') }}
                </Button>
            </template>
        </section>

        <h2 class="mb-3 font-display text-base font-semibold text-ink">{{ $t('arcadia.editor.content.stages.title') }}</h2>

        <div class="flex flex-col gap-5">
            <StageBlock v-for="(stage, index) in stages" :key="stage.id" :stage="stage" :number="index + 1"
                        :first="index === 0" :last="index === stages.length - 1" />
            <p v-if="stages.length === 0" class="panel m-0 p-5 text-sm text-ink-4">{{ $t('arcadia.editor.content.stages.empty') }}</p>
        </div>

        <template v-if="editable">
            <NodeForm v-if="addingStage" class="mt-5" node="stages" @done="addingStage = false" />
            <Button v-else type="button" variant="brand" class="mt-5" @click="addingStage = true">
                <Plus :size="16" aria-hidden="true" />
                {{ $t('arcadia.editor.content.stages.add') }}
            </Button>
        </template>

        <FixDialog :item="fixing" @close="fixing = null" />

        <ConfirmDialog :open="confirmingRelease"
                       :title="$t('arcadia.editor.versions.release.title', { number: version.number })"
                       :body="$t('arcadia.editor.versions.release.body')"
                       :confirm-label="$t('arcadia.editor.versions.release.confirm')"
                       :processing="releasing"
                       @confirm="release"
                       @cancel="confirmingRelease = false" />

        <Dialog :open="released !== null" @update:open="value => !value && (released = null)">
            <DialogContent v-if="released !== null" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('arcadia.editor.versions.released.title', { number: released }) }}</DialogTitle>
                    <DialogDescription>{{ props.guide.title }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <p class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.versions.released.body') }}</p>
                    <CodeBlock :code="props.guide.link" :copy-label="$t('arcadia.editor.guides.link.copy')" :copied-label="$t('arcadia.editor.guides.link.copied')" />
                    <!-- A guide set back to draft stays there through releases (VersionController::release). -->
                    <Badge v-if="!props.guide.readable" variant="pending" class="justify-self-start">{{ $t('arcadia.editor.guides.link.not_published') }}</Badge>
                </div>

                <DialogFooter>
                    <Button type="button" variant="brand" @click="released = null">{{ $t('arcadia.editor.close') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog :open="pendingDelete !== null"
                       :title="$t('arcadia.editor.content.delete.title')"
                       :body="$t('arcadia.editor.content.delete.body')"
                       :confirm-label="$t('arcadia.editor.content.delete.confirm')"
                       :processing="deleting"
                       @confirm="confirmDelete"
                       @cancel="pendingDelete = null" />
    </div>
</template>
