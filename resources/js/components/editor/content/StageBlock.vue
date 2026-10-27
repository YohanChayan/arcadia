<script setup>
/** One stage: its title, what the reader can do when they finish it, and its cards. */
import { inject, ref } from 'vue'
import { Plus } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import NodeForm from './NodeForm.vue'
import NodeToolbar from './NodeToolbar.vue'
import CardBlock from './CardBlock.vue'
import ActivityPanel from './ActivityPanel.vue'

defineProps({
    stage: { type: Object, required: true },
    number: { type: Number, required: true },
    first: Boolean,
    last: Boolean,
})

const content = inject('content')
const editing = ref(false)
const addingCard = ref(false)
</script>

<template>
    <section class="panel p-5 sm:p-6">
        <NodeForm v-if="editing" node="stages" :row="stage" @done="editing = false" />
        <div v-else class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="m-0 font-display text-lg font-semibold text-ink">
                    <span class="font-mono text-sm text-accent">{{ number }} ·</span> {{ stage.title }}
                </h3>
                <p v-if="stage.goal" class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.editor.content.fields.goal') }} {{ stage.goal }}</p>
            </div>
            <NodeToolbar node="stages" :row="stage" :first="first" :last="last" @edit="editing = true" />
        </div>
        <!-- Its mini-game, under its title. -->
        <ActivityPanel v-if="!editing && stage.activity" node="stages" :row="stage" class="mt-3" />

        <div class="mt-4 flex flex-col gap-3">
            <CardBlock v-for="(card, index) in stage.cards" :key="card.id" :card="card"
                       :first="index === 0" :last="index === stage.cards.length - 1" />
            <p v-if="stage.cards.length === 0" class="m-0 text-sm text-ink-4">{{ $t('arcadia.editor.content.cards.empty') }}</p>
        </div>

        <template v-if="content.editable">
            <NodeForm v-if="addingCard" class="mt-3" node="cards" :parent-id="stage.id" @done="addingCard = false" />
            <Button v-else type="button" variant="outline" size="sm" class="mt-3" @click="addingCard = true">
                <Plus :size="14" aria-hidden="true" />
                {{ $t('arcadia.editor.content.cards.add') }}
            </Button>
        </template>
    </section>
</template>
