<script setup>
/**
 * Add or rename one vocabulary term — a category or a topic — in a dialog, as a focused task
 * over the list.
 */
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    open: { type: Boolean, default: false },
    vocabulary: { type: String, required: true },
    // The term being renamed, or null to add one.
    term: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const editorRoute = useEditorRoute()

const form = useForm({ name: '' })

watch(() => props.open, open => {
    if (!open) return
    form.defaults({ name: props.term?.name ?? '' }).reset()
    form.clearErrors()
})

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('close') }

    props.term
        ? form.put(editorRoute('vocabulary.update', { vocabulary: props.vocabulary, term: props.term.id }), options)
        : form.post(editorRoute('vocabulary.store', { vocabulary: props.vocabulary }), options)
}
</script>

<template>
    <Dialog :open="open" @update:open="value => !value && emit('close')">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t(`arcadia.editor.vocabulary.tabs.${vocabulary}`) }}</DialogTitle>
                <DialogDescription>{{ $t(`arcadia.editor.vocabulary.hints.${vocabulary}`) }}</DialogDescription>
            </DialogHeader>

            <form :id="`term-${vocabulary}`" class="grid gap-4" @submit.prevent="submit">
                <Field :data-invalid="form.errors.name || form.errors.slug ? true : undefined">
                    <FieldLabel :for="`term-${vocabulary}-name`"><Eyebrow as="span">{{ $t('arcadia.editor.vocabulary.fields.name') }}</Eyebrow></FieldLabel>
                    <Input :id="`term-${vocabulary}-name`" v-model="form.name" autocomplete="off"
                           :aria-invalid="form.errors.name || form.errors.slug ? 'true' : undefined" />
                    <FieldError v-if="form.errors.name || form.errors.slug">{{ form.errors.name || form.errors.slug }}</FieldError>
                </Field>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emit('close')">{{ $t('arcadia.editor.cancel') }}</Button>
                <Button type="submit" :form="`term-${vocabulary}`" variant="brand" :disabled="form.processing || !form.name">
                    {{ $t('arcadia.editor.vocabulary.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
