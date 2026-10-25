<script setup>
/**
 * Move up, move down, edit and delete for one node. Absent on a released version, which is
 * frozen — the server would refuse anyway.
 */
import { inject } from 'vue'
import { ArrowUp, ArrowDown, Pencil, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    node: { type: String, required: true },
    row: { type: Object, required: true },
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
})

defineEmits(['edit'])
const content = inject('content')
</script>

<template>
    <div v-if="content.editable" class="flex shrink-0 gap-0.5">
        <Button type="button" variant="ghost" size="icon-sm" :disabled="first" @click="content.move(node, row, 'up')" :aria-label="$t('arcadia.editor.content.actions.up')">
            <ArrowUp :size="14" aria-hidden="true" />
        </Button>
        <Button type="button" variant="ghost" size="icon-sm" :disabled="last" @click="content.move(node, row, 'down')" :aria-label="$t('arcadia.editor.content.actions.down')">
            <ArrowDown :size="14" aria-hidden="true" />
        </Button>
        <Button type="button" variant="ghost" size="icon-sm" @click="$emit('edit')" :aria-label="$t('arcadia.editor.content.actions.edit')">
            <Pencil :size="14" aria-hidden="true" />
        </Button>
        <Button type="button" variant="ghost-danger" size="icon-sm" @click="content.requestDelete(node, row)" :aria-label="$t('arcadia.editor.content.actions.delete')">
            <Trash2 :size="14" aria-hidden="true" />
        </Button>
    </div>
</template>
