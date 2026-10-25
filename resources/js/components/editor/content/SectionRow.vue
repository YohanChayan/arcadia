<script setup>
/** One prose block of the guide — how to use it, going further, tips. */
import { inject, ref } from 'vue'
import NodeForm from './NodeForm.vue'
import NodeToolbar from './NodeToolbar.vue'

defineProps({
    section: { type: Object, required: true },
    first: Boolean,
    last: Boolean,
})

const content = inject('content')
const editing = ref(false)
</script>

<template>
    <NodeForm v-if="editing" node="sections" :row="section" @done="editing = false" />
    <div v-else class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
            <div class="text-xs font-medium uppercase tracking-wide text-accent">{{ content.label('sections', section.kind) }}</div>
            <div v-if="section.title" class="mt-1 font-medium text-ink">{{ section.title }}</div>
            <p class="mt-1 mb-0 whitespace-pre-line text-sm text-ink-2">{{ section.body }}</p>
        </div>
        <NodeToolbar node="sections" :row="section" :first="first" :last="last" @edit="editing = true" />
    </div>
</template>
