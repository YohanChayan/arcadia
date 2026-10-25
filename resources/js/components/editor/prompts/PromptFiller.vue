<script setup>
/**
 * A prompt version, filled in: a field per declared variable, in order, and beside it the prompt
 * as it will be copied — rendered by the server (`PromptRenderer`), so what is shown is exactly
 * what is copied. The request's popup and the template's "Probar" panel are both this.
 *
 * Copy is held back while a required blank is empty or a render is still on its way, so a
 * prompt is never copied with a hole in it or out of date. Copying emits `copied` with the
 * version and values, for whoever wants to record them.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Check, Copy, TriangleAlert } from '@lucide/vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    templateId: { type: Number, required: true },
    // { id, number, frozen, variables: [{ name, label, type, required, default }] }
    version: { type: Object, required: true },
    // The options of each choice type: level, language, category.
    choices: { type: Object, required: true },
    // Values to start from, by variable name — ahead of each variable's own default.
    initial: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['copied'])

const editorRoute = useEditorRoute()

// Every form also carries a one-off line for this research, added at the end of the prompt and
// kept with the values (`PromptRenderer::EXTRA`).
const EXTRA = 'extra_instructions'

// Reka's Select cannot hold an empty value, so "nothing chosen" travels as this and is sent as ''.
const NONE = '__none__'
const CHOICE_TYPES = ['level', 'language', 'category']

const values = reactive({})

const reset = () => {
    for (const key of Object.keys(values)) {
        delete values[key]
    }
    for (const variable of props.version.variables) {
        values[variable.name] = props.initial[variable.name] ?? variable.default ?? ''
    }
    values[EXTRA] = props.initial[EXTRA] ?? ''
}

const isChoice = variable => CHOICE_TYPES.includes(variable.type) && (props.choices[variable.type] ?? []).length > 0

const selectValue = name => computed({
    get: () => values[name] || NONE,
    set: value => { values[name] = value === NONE ? '' : value },
})

const selects = computed(() => Object.fromEntries(props.version.variables.map(variable => [variable.name, selectValue(variable.name)])))

const result = ref({ prompt: '', missing: [], undeclared: [] })
const pending = ref(false)
const failed = ref(false)
let timer = null
let ticket = 0

const render = () => {
    clearTimeout(timer)
    pending.value = true
    timer = setTimeout(async () => {
        const mine = ++ticket
        try {
            const { data } = await window.axios.post(editorRoute('prompts.fill', props.templateId), {
                version_id: props.version.id,
                values: { ...values },
            })
            // An older answer arriving after a newer request is dropped.
            if (mine === ticket) {
                result.value = data
                failed.value = false
            }
        } catch {
            if (mine === ticket) {
                failed.value = true
            }
        } finally {
            if (mine === ticket) {
                pending.value = false
            }
        }
    }, 300)
}

watch(() => [props.version.id, props.initial], () => {
    reset()
    render()
}, { immediate: true })

watch(values, render, { deep: true })

const label = name => props.version.variables.find(variable => variable.name === name)?.label ?? name
const missingLabels = computed(() => result.value.missing.map(label).join(', '))

const canCopy = computed(() => !pending.value && !failed.value && result.value.missing.length === 0 && result.value.prompt !== '')

const copied = ref(false)
let copiedTimer = null

const copy = async () => {
    try {
        await navigator.clipboard.writeText(result.value.prompt)
    } catch {
        // No clipboard permission, or an insecure origin: nothing was copied, so nothing is recorded.
        return
    }

    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copied.value = false }, 2000)

    emit('copied', { version_id: props.version.id, values: { ...values } })
}

onBeforeUnmount(() => {
    clearTimeout(timer)
    clearTimeout(copiedTimer)
})

const fieldId = name => `fill-${props.version.id}-${name}`
</script>

<template>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
        <div class="space-y-4">
            <p v-if="!version.variables.length" class="m-0 text-sm text-ink-4">{{ $t('arcadia.editor.prompts.fill.no_variables') }}</p>

            <Field v-for="variable in version.variables" :key="variable.name">
                <FieldLabel :for="fieldId(variable.name)">
                    <Eyebrow as="span">{{ variable.label }}</Eyebrow>
                    <span v-if="!variable.required" class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                </FieldLabel>

                <Select v-if="isChoice(variable)" v-model="selects[variable.name].value">
                    <SelectTrigger :id="fieldId(variable.name)"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">—</SelectItem>
                        <SelectItem v-for="option in choices[variable.type]" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <Textarea v-else-if="variable.type === 'longtext'" :id="fieldId(variable.name)" v-model="values[variable.name]" rows="3" />
                <Input v-else :id="fieldId(variable.name)" v-model="values[variable.name]" type="text" autocomplete="off" />
            </Field>

            <Field class="border-t border-rule pt-4">
                <FieldLabel :for="fieldId(EXTRA)">
                    <Eyebrow as="span">{{ $t('arcadia.editor.prompts.fill.extra') }}</Eyebrow>
                    <span class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                </FieldLabel>
                <Textarea :id="fieldId(EXTRA)" v-model="values[EXTRA]" rows="3" :placeholder="$t('arcadia.editor.prompts.fill.extra_placeholder')" />
            </Field>
        </div>

        <div class="min-w-0">
            <div class="mb-2 flex items-center justify-between gap-3">
                <Eyebrow as="span">
                    {{ $t('arcadia.editor.prompts.fill.preview', { number: version.number }) }}
                </Eyebrow>
                <Button type="button" variant="brand" size="sm" :disabled="!canCopy" @click="copy">
                    <component :is="copied ? Check : Copy" :size="14" aria-hidden="true" />
                    {{ copied ? $t('arcadia.editor.prompts.fill.copied') : $t('arcadia.editor.prompts.fill.copy') }}
                </Button>
            </div>

            <p v-if="result.missing.length" class="mt-0 mb-2 flex items-center gap-1.5 text-xs text-warning">
                <TriangleAlert :size="13" aria-hidden="true" />
                {{ $t('arcadia.editor.prompts.fill.missing', { names: missingLabels }) }}
            </p>
            <p v-if="failed" class="mt-0 mb-2 text-xs text-danger">{{ $t('arcadia.editor.prompts.fill.failed') }}</p>

            <div class="relative">
                <pre class="m-0 max-h-[28rem] min-h-40 overflow-auto rounded-control border border-rule bg-well px-3 py-2.5 font-mono text-xs leading-relaxed whitespace-pre-wrap text-ink-2"
                     :class="pending ? 'opacity-60' : ''">{{ result.prompt }}</pre>
                <Spinner v-if="pending" class="absolute top-2.5 right-2.5 size-4 text-ink-4" />
            </div>
        </div>
    </div>
</template>
