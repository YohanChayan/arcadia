<script setup>
/**
 * Repair one line in every version that carries it — the video was taken down, the site moved.
 * It reaches released versions too, which is why it says how many it will touch first. A link
 * with a brief shows it above the form, with the way to a replacement: the brief is what the new
 * link must teach, and the repair keeps it.
 *
 * A video's clip is here too. It belongs to the video, not the lesson: a new link whose clip is
 * left as it was plays whole (`Item::booted()`), so a replacement gets its own clip or none.
 */
import { inject, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import Eyebrow from '@/components/common/Eyebrow.vue'
import ItemBrief from './ItemBrief.vue'
import { clock } from '@/lib/clock.js'

const props = defineProps({
    item: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const content = inject('content')

const form = useForm({ title: '', url: '', hint: '', body: '', is_available: true, clip_start: '', clip_end: '', clip_evidence: '' })

const time = seconds => seconds == null ? '' : clock(seconds)

// Refilled from the line each time the dialog opens on one.
watch(() => props.item, item => {
    if (!item) return
    form.defaults({
        title: item.title ?? '',
        url: item.url ?? '',
        hint: item.hint ?? '',
        body: item.body ?? '',
        is_available: item.is_available,
        clip_start: time(item.clip_start),
        clip_end: time(item.clip_end),
        clip_evidence: item.clip_evidence ?? '',
    }).reset()
    form.clearErrors()
})

const submit = () => form.put(content.route('fix', props.item.key), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
})
</script>

<template>
    <Dialog :open="item !== null" @update:open="open => !open && emit('close')">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.versions.fix.title') }}</DialogTitle>
                <DialogDescription v-if="item">
                    {{ $tChoice('arcadia.editor.versions.fix.body', content.copies[item.key] ?? 1, { count: content.copies[item.key] ?? 1 }) }}
                </DialogDescription>
            </DialogHeader>

            <ItemBrief v-if="item?.brief && content.linkKinds.includes(item.kind)" :item="item" />

            <form v-if="item" id="fix-form" class="grid gap-4" @submit.prevent="submit">
                <Field :data-invalid="form.errors.title ? true : undefined">
                    <FieldLabel for="fix-title"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.title') }}</Eyebrow></FieldLabel>
                    <Input id="fix-title" v-model="form.title" />
                    <FieldError v-if="form.errors.title">{{ form.errors.title }}</FieldError>
                </Field>
                <Field v-if="content.linkKinds.includes(item.kind)" :data-invalid="form.errors.url ? true : undefined">
                    <FieldLabel for="fix-url"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.url') }}</Eyebrow></FieldLabel>
                    <Input id="fix-url" v-model="form.url" type="url" />
                    <FieldError v-if="form.errors.url">{{ form.errors.url }}</FieldError>
                </Field>
                <div v-if="item.kind === 'youtube_video'" class="grid grid-cols-2 gap-4">
                    <Field :data-invalid="form.errors.clip_start ? true : undefined">
                        <FieldLabel for="fix-clip-start"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.clip_start') }}</Eyebrow></FieldLabel>
                        <Input id="fix-clip-start" v-model="form.clip_start" placeholder="14:00" autocomplete="off" />
                        <FieldError v-if="form.errors.clip_start">{{ form.errors.clip_start }}</FieldError>
                    </Field>
                    <Field :data-invalid="form.errors.clip_end ? true : undefined">
                        <FieldLabel for="fix-clip-end"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.clip_end') }}</Eyebrow></FieldLabel>
                        <Input id="fix-clip-end" v-model="form.clip_end" placeholder="18:00" autocomplete="off" />
                        <FieldError v-if="form.errors.clip_end">{{ form.errors.clip_end }}</FieldError>
                    </Field>
                    <Field class="col-span-2" :data-invalid="form.errors.clip_evidence ? true : undefined">
                        <FieldLabel for="fix-clip-evidence"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.clip_evidence') }}</Eyebrow></FieldLabel>
                        <Textarea id="fix-clip-evidence" v-model="form.clip_evidence" rows="2" />
                        <FieldError v-if="form.errors.clip_evidence">{{ form.errors.clip_evidence }}</FieldError>
                    </Field>
                </div>
                <Field>
                    <FieldLabel for="fix-hint"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.hint') }}</Eyebrow></FieldLabel>
                    <Input id="fix-hint" v-model="form.hint" />
                </Field>
                <Field>
                    <FieldLabel for="fix-body"><Eyebrow as="span">{{ $t('arcadia.editor.content.fields.body') }}</Eyebrow></FieldLabel>
                    <Textarea id="fix-body" v-model="form.body" rows="3" />
                </Field>
                <div class="flex items-center gap-2">
                    <Checkbox id="fix-available" v-model="form.is_available" />
                    <Label for="fix-available" class="cursor-pointer font-normal text-ink-2">{{ $t('arcadia.editor.content.fields.is_available') }}</Label>
                </div>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emit('close')">{{ $t('arcadia.editor.content.actions.cancel') }}</Button>
                <Button type="submit" form="fix-form" variant="brand" :disabled="form.processing">{{ $t('arcadia.editor.versions.fix.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
