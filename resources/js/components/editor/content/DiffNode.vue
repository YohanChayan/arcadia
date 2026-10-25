<script setup>
/**
 * One node of a version comparison, and the nodes under it — a stage holds cards, a card holds
 * items. Its status says what happened to it; a changed node lists each field before → after.
 * Unchanged nodes with nothing changed under them are folded unless asked for.
 */
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Badge } from '@/components/ui/badge'

const props = defineProps({
    node: { type: Object, required: true },
    level: { type: String, required: true },
    labels: { type: Object, required: true },
    showUnchanged: { type: Boolean, default: false },
})

const CHILD = { stages: 'cards', cards: 'items' }

const visible = computed(() => props.showUnchanged || props.node.touched)

const heading = computed(() => {
    const n = props.node.node
    switch (props.level) {
        case 'sections': return [props.labels.sections[n.kind], n.title].filter(Boolean).join(' · ')
        case 'stages': return n.title
        case 'cards': return [props.labels.cards[n.kind], n.byline].filter(Boolean).join(' · ')
        default: return n.title
    }
})

const STATUS_BADGE = { added: 'ok', removed: 'off', changed: 'pending' }

const STATUS_TONE = {
    added: 'border-success bg-success/5',
    removed: 'border-danger bg-danger/5',
    changed: 'border-signal bg-signal/5',
    unchanged: 'border-rule',
}

const show = (field, value) => {
    if (value === null || value === '' || value === undefined) return '—'
    if (value === true) return trans('arcadia.editor.content.fields.yes')
    if (value === false) return trans('arcadia.editor.content.fields.no')
    // A kind is stored as its code; show its label.
    return field === 'kind' ? props.labels[props.level]?.[value] ?? value : value
}
</script>

<template>
    <div v-if="visible" class="border-l-2 pl-3" :class="STATUS_TONE[node.status]">
        <div class="flex flex-wrap items-center gap-2 py-1">
            <span class="text-sm" :class="[node.status === 'removed' ? 'text-ink-3 line-through' : 'text-ink', level === 'stages' ? 'font-display font-semibold' : 'font-medium']">
                {{ heading }}
            </span>
            <Badge v-if="level === 'items'" variant="neutral">{{ labels.items[node.node.kind] }}</Badge>
            <Badge v-if="node.status !== 'unchanged'" :variant="STATUS_BADGE[node.status]">
                {{ $t(`arcadia.editor.compare.status.${node.status}`) }}
            </Badge>
            <Badge v-if="node.moved" variant="active">{{ $t('arcadia.editor.compare.status.moved') }}</Badge>
            <Badge v-else-if="node.reordered" variant="active">{{ $t('arcadia.editor.compare.status.reordered') }}</Badge>
        </div>

        <dl v-if="Object.keys(node.changed).length" class="mt-1 mb-2 grid gap-1 text-xs">
            <div v-for="(change, field) in node.changed" :key="field" class="grid grid-cols-[auto_1fr] gap-x-3">
                <dt class="text-ink-4">{{ $t(`arcadia.editor.content.fields.${field}`) }}</dt>
                <dd class="m-0 min-w-0">
                    <span class="whitespace-pre-line break-words text-danger line-through">{{ show(field, change.from) }}</span>
                    <span class="mx-1 text-ink-4" aria-hidden="true">→</span>
                    <span class="whitespace-pre-line break-words text-success">{{ show(field, change.to) }}</span>
                </dd>
            </div>
        </dl>

        <div v-if="CHILD[level] && node.children.length" class="mt-1 flex flex-col gap-1.5">
            <DiffNode v-for="child in node.children" :key="child.key" :node="child" :level="CHILD[level]"
                      :labels="labels" :show-unchanged="showUnchanged" />
        </div>
    </div>
</template>
