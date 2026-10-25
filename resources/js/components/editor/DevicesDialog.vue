<script setup>
/**
 * One reader's devices, from a guide's Readers screen (option D): each browser their account
 * reads on, with a revoke — that browser is signed out on its next request — and this account's
 * own device limit, which blank returns to the site's. Per account: the same devices show on
 * every guide the reader has.
 */
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // The reader row: { email, user_id, devices: [{ id, label, created_at, last_used_at }], device_limit }, or null.
    reader: { type: Object, default: null },
    // The site's limit, shown when the reader has none of their own.
    siteLimit: { type: Number, required: true },
})

const emit = defineEmits(['close'])

const editorRoute = useEditorRoute()

const form = useForm({ limit: '' })

watch(() => props.reader, reader => {
    form.clearErrors()
    form.limit = reader?.device_limit ?? ''
}, { immediate: true })

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

const revoking = ref(null)

const revoke = device => {
    revoking.value = device.id
    router.delete(editorRoute('devices.destroy', { user: props.reader.user_id, device: device.id }), {
        preserveScroll: true,
        onFinish: () => { revoking.value = null },
    })
}

const saveLimit = () => form
    .transform(data => ({ limit: data.limit === '' ? null : Number(data.limit) }))
    .put(editorRoute('devices.limit', { user: props.reader.user_id }), { preserveScroll: true })
</script>

<template>
    <Dialog :open="reader !== null" @update:open="isOpen => !isOpen && emit('close')">
        <DialogContent v-if="reader" class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.devices.title') }}</DialogTitle>
                <DialogDescription class="break-all">{{ reader.email }}</DialogDescription>
            </DialogHeader>

            <p v-if="!reader.devices.length" class="m-0 py-4 text-center text-sm text-ink-4">{{ $t('arcadia.editor.devices.empty') }}</p>
            <ul v-else class="m-0 divide-y divide-rule rounded-control border border-rule p-0">
                <li v-for="device in reader.devices" :key="device.id" class="flex list-none items-center justify-between gap-3 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="m-0 truncate text-sm font-medium text-ink">{{ device.label }}</p>
                        <p class="mt-0.5 mb-0 text-xs text-ink-4">
                            {{ $t('arcadia.editor.devices.used', { first: formatDate(device.created_at), last: formatDate(device.last_used_at) }) }}
                        </p>
                    </div>
                    <Button type="button" variant="ghost-danger" size="sm" class="shrink-0" :disabled="revoking !== null" @click="revoke(device)">
                        <Trash2 :size="14" aria-hidden="true" />
                        {{ $t('arcadia.editor.devices.revoke') }}
                    </Button>
                </li>
            </ul>

            <form class="mt-2 flex items-end gap-2" @submit.prevent="saveLimit">
                <Field class="flex-1" :data-invalid="form.errors.limit ? true : undefined">
                    <FieldLabel for="device-limit">{{ $t('arcadia.editor.devices.limit') }}</FieldLabel>
                    <Input id="device-limit" v-model="form.limit" type="number" min="1" max="20" inputmode="numeric"
                           :placeholder="String(siteLimit)" :aria-invalid="form.errors.limit ? 'true' : undefined" />
                    <FieldError v-if="form.errors.limit">{{ form.errors.limit }}</FieldError>
                    <FieldDescription v-else>{{ $t('arcadia.editor.devices.limit_hint', { count: siteLimit }) }}</FieldDescription>
                </Field>
                <Button type="submit" variant="outline" :disabled="form.processing" class="mb-6">{{ $t('arcadia.editor.devices.save') }}</Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
