<script setup>
/**
 * One prompt template, open on a version: its declared variables and its ordered sections,
 * edited in place on the latest version until it is used. Any other version is read-only
 * history, and "New version" copies the latest to keep working — the only way on once it is
 * frozen. "Probar", in the header, fills the open version in a popup as a request's does, without
 * recording anything.
 * Every version, frozen or not, carries a short description — what it is for, which approach it
 * takes — for where versions are picked (a guide's Presentation screen).
 */
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { ArrowDown, ArrowUp, CopyPlus, FlaskConical, Lock, Pencil, Plus, Trash2, TriangleAlert } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SectionForm from '@/components/editor/prompts/SectionForm.vue'
import VariableForm from '@/components/editor/prompts/VariableForm.vue'
import PromptFiller from '@/components/editor/prompts/PromptFiller.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    template: { type: Object, required: true },
    version: { type: Object, required: true },
    versions: { type: Array, required: true },
    sections: { type: Array, required: true },
    variables: { type: Array, required: true },
    // Placeholders the sections use that no variable declares.
    undeclared: { type: Array, required: true },
    kinds: { type: Array, required: true },
    types: { type: Array, required: true },
    // This version as the "Probar" panel fills it, and the choices its fields offer.
    fillVersion: { type: Object, required: true },
    choices: { type: Object, required: true },
})

const editorRoute = useEditorRoute()

const kindLabel = value => props.kinds.find(kind => kind.value === value)?.label ?? value
const typeLabel = value => props.types.find(type => type.value === value)?.label ?? value

// The one variable open for editing — an id, `new`, or none — and the placeholder a new one
// was started from, if any.
const editingVariable = ref(null)
const suggestedName = ref('')
const declare = name => {
    suggestedName.value = name
    editingVariable.value = 'new'
}

const moveVariable = (variable, direction) => router.post(
    editorRoute('prompts.variables.move', { template: props.template.id, variable: variable.id, direction }),
    {},
    { preserveScroll: true },
)

const pendingVariable = ref(null)
const deletingVariable = ref(false)
const deleteVariable = () => {
    deletingVariable.value = true
    router.delete(editorRoute('prompts.variables.destroy', { template: props.template.id, variable: pendingVariable.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingVariable.value = false
            pendingVariable.value = null
        },
    })
}

const trying = ref(false)

// A template a guide was produced from keeps that guide's provenance, so it cannot be deleted.
const hasFrozen = computed(() => props.versions.some(version => version.frozen_at))

// The one section open for editing — an id, `new`, or none.
const editing = ref(null)

const versionUrl = number => editorRoute('prompts.show', { template: props.template.id, version: number })

const newVersion = () => router.post(editorRoute('prompts.versions.store', props.template.id))

const move = (section, direction) => router.post(
    editorRoute('prompts.sections.move', { template: props.template.id, section: section.id, direction }),
    {},
    { preserveScroll: true },
)

const pendingSection = ref(null)
const deletingSection = ref(false)
const deleteSection = () => {
    deletingSection.value = true
    router.delete(editorRoute('prompts.sections.destroy', { template: props.template.id, section: pendingSection.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingSection.value = false
            pendingSection.value = null
        },
    })
}

const renaming = ref(false)
const details = useForm({ name: props.template.name, description: props.template.description ?? '' })
const saveDetails = () => details.put(editorRoute('prompts.update', props.template.id), {
    preserveScroll: true,
    onSuccess: () => {
        renaming.value = false
    },
})

const describing = ref(false)
const description = useForm({ description: props.version.description ?? '' })
const openDescribing = () => {
    description.defaults({ description: props.version.description ?? '' })
    description.reset()
    describing.value = true
}
const saveDescription = () => description.put(editorRoute('prompts.versions.update', { template: props.template.id, version: props.version.id }), {
    preserveScroll: true,
    onSuccess: () => (describing.value = false),
})

const confirmingDelete = ref(false)
const deletingTemplate = ref(false)
const deleteTemplate = () => {
    deletingTemplate.value = true
    router.delete(editorRoute('prompts.destroy', props.template.id), {
        onFinish: () => {
            deletingTemplate.value = false
            confirmingDelete.value = false
        },
    })
}
</script>

<template>
    <EditorTitle :parts="[template.name, $t('arcadia.editor.prompts.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.prompts.title'), route: 'prompts.index' }, { label: template.name }]" />

        <PageHeader :eyebrow="$t('arcadia.editor.prompts.title')" :title="template.name" :description="template.description ?? ''">
            <template #actions>
                <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.rename')" @click="renaming = true">
                    <Pencil :size="14" aria-hidden="true" />
                </Button>
                <Button v-if="!hasFrozen" type="button" variant="ghost-danger" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.delete.action')"
                        @click="confirmingDelete = true">
                    <Trash2 :size="14" aria-hidden="true" />
                </Button>
                <Button type="button" variant="outline" @click="trying = true">
                    <FlaskConical :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.prompts.try.open') }}
                </Button>
                <Button type="button" variant="outline" @click="newVersion">
                    <CopyPlus :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.prompts.new_version') }}
                </Button>
            </template>
        </PageHeader>

        <!-- The versions, newest last; the open one lit. -->
        <nav class="mb-5 flex flex-wrap items-center gap-2" :aria-label="$t('arcadia.editor.prompts.versions')">
            <Eyebrow as="span" class="mr-1">{{ $t('arcadia.editor.prompts.versions') }}</Eyebrow>
            <Link v-for="item in versions" :key="item.id" :href="versionUrl(item.number)" :title="item.description ?? undefined"
                  class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 font-mono text-xs no-underline transition-colors"
                  :class="item.id === version.id ? 'border-accent bg-accent/15 text-ink' : 'border-rule text-ink-3 hover:border-accent hover:text-ink'">
                v{{ item.number }}
                <Lock v-if="item.frozen_at" :size="11" aria-hidden="true" />
            </Link>
        </nav>

        <!-- What the open version is for: a label, editable even once frozen. -->
        <form v-if="describing" class="mb-5 flex max-w-2xl flex-wrap items-start gap-2" @submit.prevent="saveDescription">
            <Field class="min-w-0 flex-1" :data-invalid="description.errors.description ? true : undefined">
                <FieldLabel for="version-description" class="sr-only">{{ $t('arcadia.editor.prompts.version_description.label') }}</FieldLabel>
                <Input id="version-description" v-model="description.description" type="text" maxlength="255" autocomplete="off"
                       :placeholder="$t('arcadia.editor.prompts.version_description.placeholder')" />
                <FieldError v-if="description.errors.description">{{ description.errors.description }}</FieldError>
            </Field>
            <Button type="button" variant="ghost" @click="describing = false">{{ $t('arcadia.editor.cancel') }}</Button>
            <Button type="submit" variant="brand" :disabled="description.processing">{{ $t('arcadia.editor.prompts.save') }}</Button>
        </form>
        <p v-else class="mb-5 flex max-w-2xl items-center gap-2 text-sm" :class="version.description ? 'text-ink-2' : 'text-ink-4'">
            <span class="font-mono text-xs text-ink-4">v{{ version.number }}</span>
            {{ version.description || $t('arcadia.editor.prompts.version_description.none') }}
            <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.version_description.edit')" @click="openDescribing">
                <Pencil :size="14" aria-hidden="true" />
            </Button>
        </p>

        <p v-if="!version.editable" class="panel mb-5 flex items-start gap-2 px-4 py-3 text-sm text-ink-2">
            <Lock :size="16" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
            <span>
                {{ version.frozen ? $t('arcadia.editor.prompts.frozen_notice') : $t('arcadia.editor.prompts.history_notice') }}
                <Link v-if="!version.latest" :href="versionUrl(versions[versions.length - 1].number)" class="ml-1 text-accent">
                    {{ $t('arcadia.editor.prompts.open_latest') }}
                </Link>
            </span>
        </p>

        <!-- The blanks: what the fill-in form asks for, in order. -->
        <section class="panel mb-6 p-4 sm:p-5" aria-labelledby="variables-title">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <Eyebrow id="variables-title" as="h2">{{ $t('arcadia.editor.prompts.variables.title') }}</Eyebrow>
                <Button v-if="version.editable && editingVariable !== 'new'" type="button" variant="ghost" size="sm" @click="declare('')">
                    <Plus :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.prompts.variables.add') }}
                </Button>
            </div>

            <p v-if="undeclared.length" class="mt-3 mb-0 flex flex-wrap items-center gap-1.5 text-xs text-warning">
                <TriangleAlert :size="13" aria-hidden="true" />
                {{ $t('arcadia.editor.prompts.variables.undeclared') }}
                <template v-for="name in undeclared" :key="name">
                    <button v-if="version.editable" type="button" class="rounded-full border border-warning/40 px-2 py-0.5 font-mono text-warning hover:bg-warning/10"
                            @click="declare(name)">{{ name }}</button>
                    <code v-else class="font-mono">{{ name }}</code>
                </template>
            </p>

            <p v-if="!variables.length && editingVariable !== 'new'" class="mt-3 mb-0 text-sm text-ink-4">{{ $t('arcadia.editor.prompts.variables.none') }}</p>

            <ul v-if="variables.length" class="mt-3 mb-0 divide-y divide-rule p-0">
                <li v-for="(variable, index) in variables" :key="variable.id" class="list-none py-2.5">
                    <VariableForm v-if="editingVariable === variable.id" :template-id="template.id" :types="types" :variable="variable" @done="editingVariable = null" />
                    <div v-else class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <code class="font-mono text-sm text-accent">{{ '{' + '{' + variable.name + '}' + '}' }}</code>
                        <span class="text-sm text-ink">{{ variable.label }}</span>
                        <Badge variant="neutral">{{ typeLabel(variable.type) }}</Badge>
                        <Badge v-if="variable.required" variant="active">{{ $t('arcadia.editor.prompts.variables.required_badge') }}</Badge>

                        <div v-if="version.editable" class="ml-auto flex items-center gap-1">
                            <Button type="button" variant="ghost" size="icon-sm" :disabled="index === 0" :aria-label="$t('arcadia.editor.prompts.move_up')" @click="moveVariable(variable, 'up')">
                                <ArrowUp :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost" size="icon-sm" :disabled="index === variables.length - 1" :aria-label="$t('arcadia.editor.prompts.move_down')" @click="moveVariable(variable, 'down')">
                                <ArrowDown :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.variables.edit')" @click="editingVariable = variable.id">
                                <Pencil :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost-danger" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.variables.delete.action')" @click="pendingVariable = variable">
                                <Trash2 :size="14" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>

            <VariableForm v-if="editingVariable === 'new'" :key="suggestedName" class="mt-3" :template-id="template.id" :types="types"
                          :suggested-name="suggestedName" @done="editingVariable = null" />
        </section>

        <ol class="m-0 space-y-3 p-0">
            <li v-for="(section, index) in sections" :key="section.id" class="list-none">
                <SectionForm v-if="editing === section.id" :template-id="template.id" :kinds="kinds" :section="section" @done="editing = null" />

                <article v-else class="panel p-4 sm:p-5">
                    <header class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs text-ink-4">{{ String(index + 1).padStart(2, '0') }}</span>
                        <Badge variant="active">{{ kindLabel(section.kind) }}</Badge>
                        <h2 v-if="section.title" class="m-0 font-display text-base font-semibold text-ink">{{ section.title }}</h2>

                        <div v-if="version.editable" class="ml-auto flex items-center gap-1">
                            <Button type="button" variant="ghost" size="icon-sm" :disabled="index === 0" :aria-label="$t('arcadia.editor.prompts.move_up')" @click="move(section, 'up')">
                                <ArrowUp :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost" size="icon-sm" :disabled="index === sections.length - 1" :aria-label="$t('arcadia.editor.prompts.move_down')" @click="move(section, 'down')">
                                <ArrowDown :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.edit_section')" @click="editing = section.id">
                                <Pencil :size="14" aria-hidden="true" />
                            </Button>
                            <Button type="button" variant="ghost-danger" size="icon-sm" :aria-label="$t('arcadia.editor.prompts.delete_section.action')" @click="pendingSection = section">
                                <Trash2 :size="14" aria-hidden="true" />
                            </Button>
                        </div>
                    </header>

                    <pre class="mt-3 mb-0 max-h-72 overflow-auto rounded-control bg-well px-3 py-2.5 font-mono text-[0.8rem] leading-relaxed whitespace-pre-wrap text-ink-2">{{ section.body }}</pre>
                </article>
            </li>
        </ol>

        <p v-if="!sections.length && editing !== 'new'" class="panel px-4 py-8 text-center text-sm text-ink-4">{{ $t('arcadia.editor.prompts.no_sections') }}</p>

        <div v-if="version.editable" class="mt-4">
            <SectionForm v-if="editing === 'new'" :template-id="template.id" :kinds="kinds" @done="editing = null" />
            <Button v-else type="button" variant="outline" @click="editing = 'new'">
                <Plus :size="16" aria-hidden="true" />
                {{ $t('arcadia.editor.prompts.add_section') }}
            </Button>
        </div>

        <!-- Fill the open version as a request's popup does; nothing is recorded or frozen. -->
        <Dialog v-model:open="trying">
            <DialogContent class="w-[calc(100%-2rem)] max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('arcadia.editor.prompts.try.title') }} · v{{ version.number }}</DialogTitle>
                    <DialogDescription>{{ $t('arcadia.editor.prompts.try.hint') }}</DialogDescription>
                </DialogHeader>
                <PromptFiller :template-id="template.id" :version="fillVersion" :choices="choices" />
            </DialogContent>
        </Dialog>

        <ConfirmDialog :open="pendingVariable !== null"
                       :title="$t('arcadia.editor.prompts.variables.delete.title')"
                       :body="$t('arcadia.editor.prompts.variables.delete.body')"
                       :confirm-label="$t('arcadia.editor.prompts.variables.delete.confirm')"
                       :processing="deletingVariable"
                       @confirm="deleteVariable"
                       @cancel="pendingVariable = null" />

        <ConfirmDialog :open="pendingSection !== null"
                       :title="$t('arcadia.editor.prompts.delete_section.title')"
                       :body="$t('arcadia.editor.prompts.delete_section.body')"
                       :confirm-label="$t('arcadia.editor.prompts.delete_section.confirm')"
                       :processing="deletingSection"
                       @confirm="deleteSection"
                       @cancel="pendingSection = null" />

        <ConfirmDialog :open="confirmingDelete"
                       :title="$t('arcadia.editor.prompts.delete.title')"
                       :body="$t('arcadia.editor.prompts.delete.body')"
                       :confirm-label="$t('arcadia.editor.prompts.delete.confirm')"
                       :processing="deletingTemplate"
                       @confirm="deleteTemplate"
                       @cancel="confirmingDelete = false" />

        <Dialog v-model:open="renaming">
            <DialogContent class="max-w-lg">
                <form @submit.prevent="saveDetails">
                    <DialogHeader>
                        <DialogTitle>{{ $t('arcadia.editor.prompts.rename') }}</DialogTitle>
                    </DialogHeader>
                    <div class="mt-5 space-y-4">
                        <Field :data-invalid="details.errors.name ? true : undefined">
                            <FieldLabel for="template-name"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.name') }}</Eyebrow></FieldLabel>
                            <Input id="template-name" v-model="details.name" type="text" maxlength="160" autocomplete="off" />
                            <FieldError v-if="details.errors.name">{{ details.errors.name }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="template-description"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.description') }}</Eyebrow></FieldLabel>
                            <Textarea id="template-description" v-model="details.description" rows="3" />
                        </Field>
                    </div>
                    <DialogFooter class="mt-6">
                        <Button type="button" variant="ghost" @click="renaming = false">{{ $t('arcadia.editor.cancel') }}</Button>
                        <Button type="submit" variant="brand" :disabled="details.processing">{{ $t('arcadia.editor.prompts.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
