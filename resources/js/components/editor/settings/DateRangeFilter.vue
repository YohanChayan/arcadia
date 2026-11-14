<script setup>
/**
 * A date window for a list on a Site settings monitoring tab: a preset, or a custom pair of days.
 *
 * A preset applies on click. A custom range applies only on "Apply", because two date fields
 * filled one at a time would otherwise fire a reload for the half-finished pair.
 */
import { computed, ref, watch } from 'vue'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'

const props = defineProps({
    // { range, from, to } — from/to are YYYY-MM-DD and only used by `custom`
    modelValue: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    ranges: { type: Array, default: () => ['24h', '7d', '30d', 'all', 'custom'] },
})

const emit = defineEmits(['update:modelValue'])

const range = ref(props.modelValue.range)
const from = ref(props.modelValue.from ?? '')
const to = ref(props.modelValue.to ?? '')

watch(() => props.modelValue, value => {
    range.value = value.range
    from.value = value.from ?? ''
    to.value = value.to ?? ''
})

const today = new Date().toISOString().slice(0, 10)
const canApply = computed(() => from.value && to.value && from.value <= to.value)

const pick = value => {
    range.value = value
    if (value !== 'custom') emit('update:modelValue', { range: value, from: null, to: null })
}

const apply = () => {
    if (canApply.value) emit('update:modelValue', { range: 'custom', from: from.value, to: to.value })
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Tabs :model-value="range" @update:model-value="pick">
            <TabsList :aria-label="$t('arcadia.editor.settings.health.filter.label')">
                <TabsTrigger v-for="key in ranges" :key="key" :value="key" class="px-2.5 text-xs">
                    {{ $t(`arcadia.editor.settings.health.filter.ranges.${key}`) }}
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <form v-if="range === 'custom'" class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <Input v-model="from" type="date" :max="to || today" class="h-8 w-36 text-xs" :aria-label="$t('arcadia.editor.settings.health.filter.from')" />
            <span class="text-xs text-ink-4">–</span>
            <Input v-model="to" type="date" :min="from" :max="today" class="h-8 w-36 text-xs" :aria-label="$t('arcadia.editor.settings.health.filter.to')" />
            <Button type="submit" variant="outline" size="sm" :disabled="!canApply || loading">
                {{ $t('arcadia.editor.settings.health.filter.apply') }}
            </Button>
        </form>

        <Spinner v-if="loading" class="size-3.5 text-ink-3" />
    </div>
</template>
