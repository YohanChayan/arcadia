<script setup>
/**
 * The owner's AI research prompts. One template serves both languages: the language is one of
 * its variables. A new template opens straight onto its first version.
 */
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { Plus } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import DataTable from '@/components/common/DataTable.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

defineProps({
    templates: { type: Array, required: true },
})

const editorRoute = useEditorRoute()

const columns = computed(() => [
    { key: 'name', label: trans('arcadia.editor.prompts.columns.name') },
    { key: 'versions', label: trans('arcadia.editor.prompts.columns.versions'), align: 'right' },
    { key: 'updated', label: trans('arcadia.editor.prompts.columns.updated'), align: 'right' },
])

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

const creating = ref(false)
const form = useForm({ name: '', description: '' })
const create = () => form.post(editorRoute('prompts.store'))
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.prompts.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.prompts.title') }]" />

        <PageHeader :title="$t('arcadia.editor.prompts.title')" :description="$t('arcadia.editor.prompts.description')" :count="templates.length">
            <template #actions>
                <Button type="button" variant="brand" @click="creating = true">
                    <Plus :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.prompts.new') }}
                </Button>
            </template>
        </PageHeader>

        <DataTable :columns="columns" :rows="templates"
                   :empty-title="$t('arcadia.editor.prompts.empty.title')"
                   :empty-hint="$t('arcadia.editor.prompts.empty.hint')">
            <template #row="{ row }">
                <td class="px-4 py-3 align-top">
                    <Link :href="editorRoute('prompts.show', row.id)" class="font-medium text-ink no-underline hover:text-accent">{{ row.name }}</Link>
                    <p v-if="row.description" class="mt-0.5 mb-0 line-clamp-2 text-xs text-ink-4">{{ row.description }}</p>
                </td>
                <td class="px-4 py-3 align-top text-right font-mono text-sm tabular-nums text-ink-3">{{ row.versions_count }}</td>
                <td class="px-4 py-3 align-top text-right font-mono text-xs text-ink-4">{{ formatDate(row.updated_at) }}</td>
            </template>
        </DataTable>

        <Dialog v-model:open="creating">
            <DialogContent class="max-w-lg">
                <form @submit.prevent="create">
                    <DialogHeader>
                        <DialogTitle>{{ $t('arcadia.editor.prompts.new') }}</DialogTitle>
                    </DialogHeader>

                    <div class="mt-5 space-y-4">
                        <Field :data-invalid="form.errors.name ? true : undefined">
                            <FieldLabel for="prompt-name"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.name') }}</Eyebrow></FieldLabel>
                            <Input id="prompt-name" v-model="form.name" type="text" maxlength="160" autocomplete="off" />
                            <FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="prompt-description">
                                <Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.description') }}</Eyebrow>
                                <span class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                            </FieldLabel>
                            <Textarea id="prompt-description" v-model="form.description" rows="3" />
                        </Field>
                    </div>

                    <DialogFooter class="mt-6">
                        <Button type="button" variant="ghost" @click="creating = false">{{ $t('arcadia.editor.cancel') }}</Button>
                        <Button type="submit" variant="brand" :disabled="form.processing">{{ $t('arcadia.editor.prompts.create') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
