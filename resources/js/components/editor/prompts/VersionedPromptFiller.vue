<script setup>
/**
 * A template filled in, in whichever of its versions the owner picks — the one asked to open on, else
 * the latest, each named by its number and description. The picker shows only when there is a
 * choice. What a guide's Presentation screen offers for its cover and its flyer, and its Content
 * screen for its research; copying passes `PromptFiller`'s `copied` on, for a screen that records it.
 */
import { computed, ref } from 'vue'
import PromptFiller from '@/components/editor/prompts/PromptFiller.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Field, FieldLabel } from '@/components/ui/field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const props = defineProps({
    // { id, name, versions: [{ id, number, description, variables }] }, oldest first.
    prompt: { type: Object, required: true },
    // The options of each choice type, and the values to start from, as `PromptFiller` takes them.
    choices: { type: Object, required: true },
    initial: { type: Object, default: () => ({}) },
    // The version to open on, when it is still one of the template's; the latest otherwise.
    versionId: { type: Number, default: null },
})

defineEmits(['copied'])

const versionId = ref(props.prompt.versions.find(item => item.id === props.versionId)?.id ?? props.prompt.versions.at(-1)?.id ?? null)
const version = computed(() => props.prompt.versions.find(item => item.id === versionId.value) ?? null)

const fieldId = `prompt-${props.prompt.id}-version`
</script>

<template>
    <div>
        <Field v-if="prompt.versions.length > 1" class="mb-4 max-w-xl">
            <FieldLabel :for="fieldId"><Eyebrow as="span">{{ $t('arcadia.editor.images.prompt.version') }}</Eyebrow></FieldLabel>
            <Select :model-value="String(versionId)" @update:model-value="id => (versionId = Number(id))">
                <SelectTrigger :id="fieldId"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="item in prompt.versions" :key="item.id" :value="String(item.id)">
                        v{{ item.number }}<template v-if="item.description"> — {{ item.description }}</template>
                    </SelectItem>
                </SelectContent>
            </Select>
        </Field>
        <PromptFiller v-if="version" :key="version.id" :template-id="prompt.id" :version="version" :choices="choices" :initial="initial"
                      @copied="payload => $emit('copied', payload)" />
    </div>
</template>
