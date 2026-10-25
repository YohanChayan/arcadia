<script setup>
/**
 * Add or edit one declared variable of a prompt version, inline: the `{{name}}` its sections
 * use, the label the form shows, what kind of field it is, whether it is required, and what its
 * field starts filled with.
 */
import { useForm } from '@inertiajs/vue3'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    templateId: { type: Number, required: true },
    types: { type: Array, required: true },
    variable: { type: Object, default: null },
    // A placeholder the sections already use, offered as the new variable's name.
    suggestedName: { type: String, default: '' },
})

const emit = defineEmits(['done'])

const editorRoute = useEditorRoute()

const form = useForm({
    name: props.variable?.name ?? props.suggestedName,
    label: props.variable?.label ?? '',
    type: props.variable?.type ?? props.types[0].value,
    required: props.variable?.required ?? false,
    default_value: props.variable?.default_value ?? '',
})

const id = name => `variable-${props.variable?.id ?? 'new'}-${name}`

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('done') }

    if (props.variable) {
        form.put(editorRoute('prompts.variables.update', { template: props.templateId, variable: props.variable.id }), options)
    } else {
        form.post(editorRoute('prompts.variables.store', { template: props.templateId }), options)
    }
}
</script>

<template>
    <form class="rounded-panel border border-accent/40 bg-well p-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field :data-invalid="form.errors.name ? true : undefined">
                <FieldLabel :for="id('name')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.variables.name') }}</Eyebrow></FieldLabel>
                <Input :id="id('name')" v-model="form.name" type="text" maxlength="40" autocomplete="off" class="font-mono" />
                <FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.prompts.variables.name_hint') }}</FieldDescription>
            </Field>

            <Field :data-invalid="form.errors.label ? true : undefined">
                <FieldLabel :for="id('label')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.variables.label') }}</Eyebrow></FieldLabel>
                <Input :id="id('label')" v-model="form.label" type="text" maxlength="160" autocomplete="off" />
                <FieldError v-if="form.errors.label">{{ form.errors.label }}</FieldError>
            </Field>

            <Field>
                <FieldLabel :for="id('type')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.variables.type') }}</Eyebrow></FieldLabel>
                <Select v-model="form.type">
                    <SelectTrigger :id="id('type')"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </Field>
        </div>

        <Field class="mt-4" :data-invalid="form.errors.default_value ? true : undefined">
            <FieldLabel :for="id('default')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.variables.default') }}</Eyebrow></FieldLabel>
            <Textarea v-if="form.type === 'longtext'" :id="id('default')" v-model="form.default_value" rows="3" />
            <Input v-else :id="id('default')" v-model="form.default_value" type="text" autocomplete="off" />
            <FieldError v-if="form.errors.default_value">{{ form.errors.default_value }}</FieldError>
            <FieldDescription v-else>{{ $t('arcadia.editor.prompts.variables.default_hint') }}</FieldDescription>
        </Field>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Checkbox :id="id('required')" v-model="form.required" />
                <Label :for="id('required')" class="cursor-pointer font-normal text-ink-2">{{ $t('arcadia.editor.prompts.variables.required') }}</Label>
            </div>
            <div class="flex gap-2">
                <Button type="button" variant="ghost" @click="emit('done')">{{ $t('arcadia.editor.cancel') }}</Button>
                <Button type="submit" variant="brand" :disabled="form.processing">{{ $t('arcadia.editor.prompts.variables.save') }}</Button>
            </div>
        </div>
    </form>
</template>
