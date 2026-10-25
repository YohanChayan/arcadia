<script setup>
/**
 * Add or edit one node of the content tree, from its field list (`fields.js`). Used inline,
 * where the node sits, so the owner never loses their place in a long guide.
 */
import { computed, inject } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FIELDS } from './fields.js'
import { clock } from '@/lib/clock.js'

const props = defineProps({
    node: { type: String, required: true },
    row: { type: Object, default: null },
    parentId: { type: Number, default: null },
})

const emit = defineEmits(['done'])
const content = inject('content')

const fields = FIELDS[props.node]

const initial = field => {
    if (props.row && props.row[field.name] !== undefined && props.row[field.name] !== null) {
        return field.type === 'clock' ? clock(props.row[field.name]) : props.row[field.name]
    }
    if (field.type === 'select') {
        return content.kinds[field.options][0]?.value
    }
    return field.default ?? ''
}

const form = useForm(Object.fromEntries(fields.map(field => [field.name, initial(field)])))

const visible = computed(() => fields.filter(field => !field.when || field.when(form.data(), content.linkKinds)))

const id = name => `${props.node}-${props.row?.id ?? 'new'}-${name}`

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('done') }

    if (props.row) {
        form.put(content.route('update', props.node, props.row.id), options)
    } else {
        form.transform(data => ({ ...data, parent_id: props.parentId })).post(content.route('store', props.node), options)
    }
}
</script>

<template>
    <form class="rounded-panel border border-accent/40 bg-well p-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <template v-for="field in visible" :key="field.name">
                <div v-if="field.type === 'checkbox'" class="flex items-center gap-2">
                    <Checkbox :id="id(field.name)" v-model="form[field.name]" />
                    <Label :for="id(field.name)" class="cursor-pointer font-normal text-ink-2">{{ $t(`arcadia.editor.content.fields.${field.name}`) }}</Label>
                </div>

                <Field v-else :data-invalid="form.errors[field.name] ? true : undefined"
                       :class="field.type === 'textarea' || field.name === 'title' || field.type === 'url' ? 'sm:col-span-2' : ''">
                    <FieldLabel :for="id(field.name)">
                        <Eyebrow as="span">{{ $t(`arcadia.editor.content.fields.${field.name}`) }}</Eyebrow>
                        <span v-if="field.optional" class="text-xs text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                    </FieldLabel>

                    <Select v-if="field.type === 'select'" v-model="form[field.name]">
                        <SelectTrigger :id="id(field.name)"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in content.kinds[field.options]" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Textarea v-else-if="field.type === 'textarea'" :id="id(field.name)" v-model="form[field.name]" rows="3" />
                    <Input v-else :id="id(field.name)" v-model="form[field.name]" :type="field.type === 'url' ? 'url' : 'text'" autocomplete="off"
                           :placeholder="field.type === 'clock' ? '14:00' : undefined"
                           :aria-invalid="form.errors[field.name] ? 'true' : undefined" />

                    <FieldError v-if="form.errors[field.name]">{{ form.errors[field.name] }}</FieldError>
                </Field>
            </template>
        </div>

        <FieldError v-if="form.errors.parent_id" class="mt-2">{{ form.errors.parent_id }}</FieldError>

        <div class="mt-4 flex gap-2">
            <Button type="submit" variant="brand" size="sm" :disabled="form.processing">{{ $t('arcadia.editor.content.actions.save') }}</Button>
            <Button type="button" variant="outline" size="sm" @click="emit('done')">{{ $t('arcadia.editor.content.actions.cancel') }}</Button>
        </div>
    </form>
</template>
