<script setup>
/**
 * The owner writes a request themselves — for a guide they want to make, or one asked for by
 * phone — so it goes through the same prompt and "Crear guía" as one from the landing page. The
 * contact starts as the owner's own email.
 */
import { watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const open = defineModel('open', { type: Boolean, default: false })

const editorRoute = useEditorRoute()
const page = usePage()

const form = useForm({ topic: '', contact: '', name: '' })

watch(open, isOpen => {
    if (isOpen) {
        form.reset()
        form.clearErrors()
        form.contact = page.props.user?.email ?? ''
    }
})

const submit = () => form.post(editorRoute('inquiries.store'), {
    preserveScroll: true,
    onSuccess: () => { open.value = false },
})
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.inquiries.create.title') }}</DialogTitle>
                <DialogDescription>{{ $t('arcadia.editor.inquiries.create.description') }}</DialogDescription>
            </DialogHeader>

            <form id="create-inquiry" class="space-y-4" @submit.prevent="submit">
                <Field :data-invalid="form.errors.topic ? true : undefined">
                    <FieldLabel for="create-topic">{{ $t('arcadia.editor.inquiries.create.topic') }}</FieldLabel>
                    <Input id="create-topic" v-model="form.topic" maxlength="200" autocomplete="off" :aria-invalid="form.errors.topic ? 'true' : undefined" />
                    <FieldError v-if="form.errors.topic">{{ form.errors.topic }}</FieldError>
                </Field>
                <Field :data-invalid="form.errors.contact ? true : undefined">
                    <FieldLabel for="create-contact">{{ $t('arcadia.editor.inquiries.create.contact') }}</FieldLabel>
                    <Input id="create-contact" v-model="form.contact" maxlength="160" autocomplete="off" :aria-invalid="form.errors.contact ? 'true' : undefined" />
                    <FieldError v-if="form.errors.contact">{{ form.errors.contact }}</FieldError>
                    <FieldDescription v-else>{{ $t('arcadia.editor.inquiries.create.contact_hint') }}</FieldDescription>
                </Field>
                <Field>
                    <FieldLabel for="create-name">{{ $t('arcadia.editor.inquiries.create.name') }}</FieldLabel>
                    <Input id="create-name" v-model="form.name" maxlength="120" autocomplete="off" />
                </Field>
            </form>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">{{ $t('arcadia.editor.inquiries.create.cancel') }}</Button>
                <Button type="submit" form="create-inquiry" variant="brand" :disabled="form.processing">{{ $t('arcadia.editor.inquiries.create.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
