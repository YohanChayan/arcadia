<script setup>
/** How much of a stage, or of the guide, is ticked — a bar and an `n of m`. */
import { computed } from 'vue'

const props = defineProps({
    done: { type: Number, required: true },
    total: { type: Number, required: true },
})

const percent = computed(() => (props.total ? Math.round((props.done / props.total) * 100) : 0))
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-guide-well" role="progressbar"
             :aria-valuenow="done" :aria-valuemin="0" :aria-valuemax="total">
            <div class="h-full rounded-full bg-guide-accent transition-[width] duration-300" :style="{ width: `${percent}%` }"></div>
        </div>
        <span class="font-mono text-xs tabular-nums text-guide-ink-soft">{{ $t('arcadia.reader.progress', { done, total }) }}</span>
    </div>
</template>
