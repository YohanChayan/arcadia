<script setup>
/** One creator's material within a stage — or the stage's final challenge — and its items. */
import { inject, ref } from 'vue'
import { Plus } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import NodeForm from './NodeForm.vue'
import NodeToolbar from './NodeToolbar.vue'
import ItemRow from './ItemRow.vue'

defineProps({
    card: { type: Object, required: true },
    first: Boolean,
    last: Boolean,
})

const content = inject('content')
const editing = ref(false)
const addingItem = ref(false)
</script>

<template>
    <div class="rounded-panel border border-rule p-4" :class="card.kind === 'challenge' ? 'border-accent/50' : ''">
        <NodeForm v-if="editing" node="cards" :row="card" @done="editing = false" />
        <div v-else class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="text-xs font-medium uppercase tracking-wide text-accent">{{ content.label('cards', card.kind) }}</div>
                <div v-if="card.byline" class="mt-1 font-medium text-ink">{{ card.byline }}</div>
                <p v-if="card.description" class="mt-1 mb-0 text-sm text-ink-2">{{ card.description }}</p>
                <blockquote v-if="card.quote" class="mt-2 mb-0 border-l-2 border-accent pl-3 text-sm italic text-ink-3">{{ card.quote }}</blockquote>
            </div>
            <NodeToolbar node="cards" :row="card" :first="first" :last="last" @edit="editing = true" />
        </div>

        <div class="mt-3 divide-y divide-rule border-t border-rule">
            <ItemRow v-for="(item, index) in card.items" :key="item.id" :item="item"
                     :first="index === 0" :last="index === card.items.length - 1" />
            <p v-if="card.items.length === 0" class="m-0 py-2 text-sm text-ink-4">{{ $t('arcadia.editor.content.items.empty') }}</p>
        </div>

        <p v-if="card.closing_note && !editing" class="mt-3 mb-0 whitespace-pre-line text-sm text-ink-3">{{ card.closing_note }}</p>

        <template v-if="content.editable">
            <NodeForm v-if="addingItem" class="mt-3" node="items" :parent-id="card.id" @done="addingItem = false" />
            <Button v-else type="button" variant="ghost" size="sm" class="mt-2" @click="addingItem = true">
                <Plus :size="14" aria-hidden="true" />
                {{ $t('arcadia.editor.content.items.add') }}
            </Button>
        </template>
    </div>
</template>
