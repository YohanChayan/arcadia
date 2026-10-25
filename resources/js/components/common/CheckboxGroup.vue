<script setup>
/**
 * Choosing any number of values from a list — a guide's topics. One `Checkbox` and
 * `Label` per option, bound to an array through `v-model`.
 *
 * Built because the library had nothing for a multiple choice, and screens had begun to
 * hand-roll it with native checkboxes and the `.chip` class.
 */
import { useId } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'

const props = defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
    // Shown in place of the list when there is nothing to choose from.
    empty: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const id = useId()

const toggle = (value, checked) => emit(
    'update:modelValue',
    checked ? [...props.modelValue, value] : props.modelValue.filter(chosen => chosen !== value),
)
</script>

<template>
    <p v-if="options.length === 0" class="m-0 text-sm text-ink-4">{{ empty }}</p>
    <div v-else class="flex flex-wrap gap-x-5 gap-y-2.5">
        <div v-for="option in options" :key="option.value" class="flex items-center gap-2">
            <Checkbox :id="`${id}-${option.value}`" :model-value="modelValue.includes(option.value)"
                      @update:model-value="checked => toggle(option.value, checked)" />
            <Label :for="`${id}-${option.value}`" class="cursor-pointer font-normal text-ink-2">{{ option.label }}</Label>
        </div>
    </div>
</template>
