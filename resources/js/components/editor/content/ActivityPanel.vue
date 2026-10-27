<script setup>
/**
 * A node's activity (`ActivityFamily`), for the owner to review under it: everything the AI wrote,
 * the answers and the brief's words each rests on included — none of which a reader is sent.
 * Folded to a line saying what it is; open, it reads as the reader will meet it.
 *
 * On a draft the owner can reword it — its text only, in place: the questions, options and
 * explanations, the game's lines, the cards, the hint and the example. Which option is correct,
 * how many there are and a card's key are the AI's answer, changed only by importing again. Or
 * remove it. A released version shows it read-only.
 */
import { computed, inject, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { trans, transChoice } from 'laravel-vue-i18n'
import { Check, ChevronDown, Pencil, Sparkles, Trash2 } from '@lucide/vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
    // items · stages · sections
    node: { type: String, required: true },
    row: { type: Object, required: true },
})

const content = inject('content')

const activity = computed(() => props.row.activity)
const open = ref(false)

const summary = computed(() => {
    const count = ['questions', 'cards', 'pairs', 'steps'].map(list => activity.value[list]?.length ?? 0).find(length => length > 0) ?? 1
    const family = trans(`arcadia.editor.activities.families.${activity.value.family}`)

    return transChoice('arcadia.editor.activities.summary', count, { family, count })
})

// Rewording: a copy of the activity, its text edited, sent whole — the server keeps only the text.
const editing = ref(false)
const form = useForm({ activity: null })

const edit = () => {
    form.activity = JSON.parse(JSON.stringify(activity.value))
    form.clearErrors()
    editing.value = true
    open.value = true
}

const save = () => form.put(content.activityRoute('update', props.node, props.row.id), {
    preserveScroll: true,
    onSuccess: () => { editing.value = false },
})

const removing = ref(false)
const remove = () => router.delete(content.activityRoute('destroy', props.node, props.row.id), {
    preserveScroll: true,
    onFinish: () => { removing.value = false },
})

const errorAt = path => form.errors[path]
</script>

<template>
    <div class="rounded-control border border-accent/30 bg-accent/5 px-3 py-2">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="inline-flex min-w-0 items-center gap-1.5 text-xs font-medium text-ink-2 hover:text-accent"
                    :aria-expanded="open" @click="open = !open">
                <Sparkles :size="13" class="shrink-0 text-accent" aria-hidden="true" />
                <span class="truncate">{{ summary }}</span>
                <ChevronDown :size="12" class="shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
            </button>
            <span class="flex-1"></span>
            <template v-if="content.editable && !editing">
                <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.activities.edit')" :title="$t('arcadia.editor.activities.edit')" @click="edit">
                    <Pencil :size="13" aria-hidden="true" />
                </Button>
                <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.editor.activities.remove')" :title="$t('arcadia.editor.activities.remove')" @click="removing = true">
                    <Trash2 :size="13" aria-hidden="true" />
                </Button>
            </template>
        </div>

        <!-- Reading it. -->
        <div v-if="open && !editing" class="mt-2 space-y-3 text-sm text-ink-2">
            <template v-if="activity.family === 'quick_check'">
                <div v-for="(question, q) in activity.questions" :key="q">
                    <p class="m-0 font-medium text-ink">{{ q + 1 }}. {{ question.prompt }}</p>
                    <ul class="mt-1 mb-0 space-y-0.5 pl-5">
                        <li v-for="(option, o) in question.options" :key="o" :class="o === question.correct ? 'font-semibold text-success' : ''">
                            <Check v-if="o === question.correct" :size="12" class="mr-1 inline" :aria-label="$t('arcadia.editor.activities.correct')" />{{ option }}
                        </li>
                    </ul>
                    <p class="mt-1 mb-0 text-xs"><span class="text-ink-4">{{ $t('arcadia.editor.activities.explanation') }}:</span> {{ question.explanation }}</p>
                    <p v-if="question.evidence" class="mt-0.5 mb-0 text-xs italic text-ink-3"><span class="not-italic text-ink-4">{{ $t('arcadia.editor.activities.evidence') }}:</span> {{ question.evidence }}</p>
                </div>
            </template>

            <template v-else-if="activity.family === 'stage_game'">
                <p class="m-0 font-medium text-ink">{{ $t(`arcadia.editor.activities.games.${activity.game}`) }}</p>
                <p v-if="activity.instructions" class="m-0 text-xs">{{ activity.instructions }}</p>
                <ul v-if="activity.pairs?.length" class="m-0 space-y-0.5 pl-5">
                    <li v-for="(pair, p) in activity.pairs" :key="p">{{ pair.left }} <span class="text-ink-4">→</span> {{ pair.right }}</li>
                </ul>
                <ol v-if="activity.steps?.length" class="m-0 space-y-0.5 pl-5">
                    <li v-for="(step, s) in activity.steps" :key="s">{{ step }}</li>
                </ol>
                <p v-if="activity.evidence" class="m-0 text-xs italic text-ink-3"><span class="not-italic text-ink-4">{{ $t('arcadia.editor.activities.evidence') }}:</span> {{ activity.evidence }}</p>
            </template>

            <template v-else-if="activity.family === 'flip_cards'">
                <div v-for="card in activity.cards" :key="card.key" class="grid gap-x-3 sm:grid-cols-2">
                    <p class="m-0 font-medium text-ink">{{ card.front }}</p>
                    <p class="m-0">{{ card.back }}</p>
                </div>
            </template>

            <template v-else-if="activity.family === 'practice_extras'">
                <p class="m-0"><span class="text-ink-4">{{ $t('arcadia.editor.activities.hint') }}:</span> {{ activity.hint }}</p>
                <p class="m-0 whitespace-pre-line"><span class="text-ink-4">{{ $t('arcadia.editor.activities.example') }}:</span> {{ activity.example }}</p>
                <p v-if="activity.rate" class="m-0 text-xs text-ink-3">{{ $t('arcadia.editor.activities.rate') }}</p>
            </template>
        </div>

        <!-- Rewording it: its text, in place. -->
        <form v-if="editing" class="mt-2 space-y-3" @submit.prevent="save">
            <template v-if="form.activity.family === 'quick_check'">
                <div v-for="(question, q) in form.activity.questions" :key="q" class="space-y-1.5">
                    <Input v-model="question.prompt" :aria-invalid="errorAt(`questions.${q}.prompt`) ? true : undefined" />
                    <div v-for="(option, o) in question.options" :key="o" class="flex items-center gap-2 pl-4">
                        <Check :size="13" class="shrink-0" :class="o === question.correct ? 'text-success' : 'invisible'" aria-hidden="true" />
                        <Input v-model="question.options[o]" :aria-invalid="errorAt(`questions.${q}.options.${o}`) ? true : undefined" />
                    </div>
                    <Textarea v-model="question.explanation" rows="2" class="ml-4 w-[calc(100%-1rem)]" :placeholder="$t('arcadia.editor.activities.explanation')" />
                </div>
            </template>

            <template v-else-if="form.activity.family === 'stage_game'">
                <Input v-model="form.activity.instructions" :placeholder="$t('arcadia.editor.activities.instructions')" />
                <div v-for="(pair, p) in form.activity.pairs ?? []" :key="`p${p}`" class="grid gap-2 sm:grid-cols-2">
                    <Input v-model="pair.left" />
                    <Input v-model="pair.right" />
                </div>
                <Input v-for="(step, s) in form.activity.steps ?? []" :key="`s${s}`" v-model="form.activity.steps[s]" />
            </template>

            <template v-else-if="form.activity.family === 'flip_cards'">
                <div v-for="card in form.activity.cards" :key="card.key" class="grid gap-2 sm:grid-cols-2">
                    <Input v-model="card.front" />
                    <Input v-model="card.back" />
                </div>
            </template>

            <template v-else-if="form.activity.family === 'practice_extras'">
                <Textarea v-model="form.activity.hint" rows="2" :placeholder="$t('arcadia.editor.activities.hint')" />
                <Textarea v-model="form.activity.example" rows="3" :placeholder="$t('arcadia.editor.activities.example')" />
            </template>

            <ul v-if="Object.keys(form.errors).length" class="m-0 space-y-0.5 pl-5 text-xs text-danger">
                <li v-for="(message, path) in form.errors" :key="path">{{ message }}</li>
            </ul>

            <div class="flex items-center gap-2">
                <Button type="submit" variant="brand" size="sm" :disabled="form.processing">{{ $t('arcadia.editor.activities.save') }}</Button>
                <Button type="button" variant="ghost" size="sm" @click="editing = false">{{ $t('arcadia.editor.cancel') }}</Button>
            </div>
        </form>

        <ConfirmDialog :open="removing" :title="$t('arcadia.editor.activities.remove')" :body="$t('arcadia.editor.activities.remove_confirm')"
                       :confirm-label="$t('arcadia.editor.activities.remove')" @confirm="remove" @cancel="removing = false" />
    </div>
</template>
