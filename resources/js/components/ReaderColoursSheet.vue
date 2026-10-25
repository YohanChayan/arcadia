<script setup>
/**
 * A reader changes their own colours for a guide, wherever they are reading it — the guide page,
 * or a card on their dashboard. The picks move the two v-models, so whoever renders the guide
 * repaints it live; nothing is saved until Save, and Cancel puts back what was saved.
 *
 * Arcadia's own chrome, not the guide's tokens: it is a control over the page, not part of it.
 */
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppearancePicker from '@/components/AppearancePicker.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    options: { type: Object, required: true },
    saveUrl: { type: String, required: true },
    // What is saved now — Cancel returns to it.
    saved: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const palette = defineModel('palette', { type: String, required: true })
const accent = defineModel('accent', { type: String, required: true })

const saving = ref(false)

watch(() => props.open, open => {
    if (open) return
    // Closed without saving: what is saved is what is shown.
    palette.value = props.saved.palette
    accent.value = props.saved.accent
})

const save = () => {
    saving.value = true
    router.put(props.saveUrl, { palette: palette.value, accent: accent.value }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="value => !value && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.my_guides.colours.title') }}</DialogTitle>
                <DialogDescription>{{ title }}</DialogDescription>
            </DialogHeader>

            <p class="m-0 text-sm text-ink-3">{{ $t('arcadia.my_guides.colours.hint') }}</p>

            <AppearancePicker v-model:palette="palette" v-model:accent="accent" :options="options" name="reader-colours" />

            <DialogFooter>
                <Button type="button" variant="outline" :disabled="saving" @click="emit('close')">{{ $t('arcadia.my_guides.colours.cancel') }}</Button>
                <Button type="button" variant="brand" :disabled="saving" @click="save">{{ $t('arcadia.my_guides.colours.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
