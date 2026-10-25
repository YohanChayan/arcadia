<script setup>
/**
 * Add or edit one section of a prompt version, inline where it sits — so the owner reads the
 * sections around it while writing it.
 */
import { useForm } from '@inertiajs/vue3'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    templateId: { type: Number, required: true },
    kinds: { type: Array, required: true },
    // The section being edited; none for a new one.
    section: { type: Object, default: null },
})

const emit = defineEmits(['done'])

const editorRoute = useEditorRoute()

const form = useForm({
    kind: props.section?.kind ?? props.kinds[0].value,
    title: props.section?.title ?? '',
    body: props.section?.body ?? '',
})

const id = name => `section-${props.section?.id ?? 'new'}-${name}`

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('done') }

    if (props.section) {
        form.put(editorRoute('prompts.sections.update', { template: props.templateId, section: props.section.id }), options)
    } else {
        form.post(editorRoute('prompts.sections.store', { template: props.templateId }), options)
    }
}
</script>

<template>
    <form class="rounded-panel border border-accent/40 bg-well p-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[14rem_1fr]">
            <Field>
                <FieldLabel :for="id('kind')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.kind') }}</Eyebrow></FieldLabel>
                <Select v-model="form.kind">
                    <SelectTrigger :id="id('kind')"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="kind in kinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </Field>

            <Field :data-invalid="form.errors.title ? true : undefined">
                <FieldLabel :for="id('title')">
                    <Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.title') }}</Eyebrow>
                    <span class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                </FieldLabel>
                <Input :id="id('title')" v-model="form.title" type="text" maxlength="160" autocomplete="off" />
                <FieldError v-if="form.errors.title">{{ form.errors.title }}</FieldError>
            </Field>
        </div>

        <Field class="mt-4" :data-invalid="form.errors.body ? true : undefined">
            <FieldLabel :for="id('body')"><Eyebrow as="span">{{ $t('arcadia.editor.prompts.fields.body') }}</Eyebrow></FieldLabel>
            <Textarea :id="id('body')" v-model="form.body" rows="10" class="font-mono text-sm"
                      :aria-invalid="form.errors.body ? 'true' : undefined" />
            <FieldError v-if="form.errors.body">{{ form.errors.body }}</FieldError>
            <FieldDescription v-else>{{ $t('arcadia.editor.prompts.fields.body_hint') }}</FieldDescription>
        </Field>

        <div class="mt-4 flex justify-end gap-2">
            <Button type="button" variant="ghost" @click="emit('done')">{{ $t('arcadia.editor.cancel') }}</Button>
            <Button type="submit" variant="brand" :disabled="form.processing">{{ $t('arcadia.editor.prompts.save_section') }}</Button>
        </div>
    </form>
</template>
