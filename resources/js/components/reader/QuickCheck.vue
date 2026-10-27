<script setup>
/**
 * A video's quick check (`ActivityFamily::QUICK_CHECK`): one to three questions under the video,
 * answered to tick it — the line's only way to be ticked. A reader's answers are checked on the
 * server (`useGuideProgress().check`), which never sends the page the right ones. A wrong answer
 * shows, under each question missed, why the right one is right, and the reader tries again: no
 * skip. Right, it says so and offers the next line.
 *
 * Answered once, it folds to a line saying so. In a locked stage (`locked`) its questions are
 * shown, to see what is coming, and cannot be answered — nor in the owner's read-only view of a
 * reader (`progress.readOnly`).
 */
import { computed, inject, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { toast } from 'vue-sonner'
import { Check, CircleHelp, X } from '@lucide/vue'

const props = defineProps({
    lineKey: { type: String, required: true },
    // [{ prompt, options }] — and, for the owner and the preview, `correct` and `explanation`.
    questions: { type: Array, required: true },
    done: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
})

const emit = defineEmits(['next'])

const progress = inject('guideProgress')
// Shown, not answered: a locked stage, or the owner viewing as a reader.
const idle = computed(() => props.locked || Boolean(progress?.readOnly))

const chosen = ref(props.questions.map(() => null))
// The last check's outcome: { passed, results } or null before one.
const outcome = ref(null)
const checking = ref(false)

const ready = computed(() => chosen.value.every(option => option !== null))

const pick = (question, option) => {
    if (outcome.value || idle.value) return
    chosen.value = chosen.value.map((current, index) => index === question ? option : current)
}

const submit = async () => {
    checking.value = true
    try {
        outcome.value = await progress.check(props.lineKey, chosen.value, props.questions)
    } catch {
        toast.error(trans('arcadia.reader.quick_check.failed'))
    } finally {
        checking.value = false
    }
}

const retry = () => {
    chosen.value = props.questions.map(() => null)
    outcome.value = null
}

// How an option looks: picked, and — once checked — right or missed.
const tone = (question, option) => {
    const picked = chosen.value[question] === option
    const result = outcome.value?.results[question]

    if (picked && result) return result.correct ? 'border-guide-done bg-guide-done/15 text-guide-ink' : 'border-guide-accent bg-guide-surface text-guide-ink-soft line-through'
    if (picked) return 'border-guide-accent bg-guide-accent/15 text-guide-ink'
    return idle.value ? 'border-guide-rule text-guide-ink' : 'border-guide-rule text-guide-ink hover:border-guide-accent'
}
</script>

<template>
    <div data-quick-check class="mt-3 rounded-xl border border-guide-rule bg-guide-well p-4">
        <p class="m-0 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-guide-accent">
            <CircleHelp :size="14" aria-hidden="true" />
            {{ $t('arcadia.reader.quick_check.title') }}
        </p>

        <!-- Answered: nothing left to do here. -->
        <p v-if="done && !outcome" class="mt-2 mb-0 flex items-center gap-1.5 text-sm text-guide-ink-soft">
            <Check :size="15" class="text-guide-done" aria-hidden="true" />
            {{ $t('arcadia.reader.quick_check.answered') }}
        </p>

        <template v-else>
            <p v-if="!outcome" class="mt-1 mb-0 text-sm text-guide-ink-soft">{{ $t(progress?.readOnly ? 'arcadia.reader.quick_check.read_only' : locked ? 'arcadia.reader.quick_check.locked' : 'arcadia.reader.quick_check.lead') }}</p>

            <fieldset v-for="(question, q) in questions" :key="q" class="m-0 mt-3 border-0 p-0">
                <legend class="mb-2 p-0 font-medium text-guide-ink">{{ question.prompt }}</legend>
                <div class="flex flex-col gap-2" role="radiogroup">
                    <button v-for="(option, o) in question.options" :key="o" type="button" role="radio"
                            :aria-checked="chosen[q] === o" :disabled="Boolean(outcome) || idle"
                            class="rounded-lg border-2 px-3 py-2 text-left text-sm transition-colors disabled:cursor-not-allowed" :class="tone(q, o)"
                            @click="pick(q, o)">
                        {{ option }}
                    </button>
                </div>
                <!-- Why, under the question missed. -->
                <p v-if="outcome && !outcome.results[q].correct" class="mt-2 mb-0 flex items-start gap-1.5 text-sm text-guide-ink">
                    <X :size="15" class="mt-0.5 shrink-0 text-guide-accent" aria-hidden="true" />
                    {{ outcome.results[q].explanation }}
                </p>
            </fieldset>

            <div v-if="!idle" class="mt-4 flex flex-wrap items-center gap-3" aria-live="polite">
                <button v-if="!outcome" type="button" :disabled="!ready || checking" @click="submit"
                        class="rounded-full bg-guide-accent px-5 py-2 text-sm font-semibold text-guide-on-accent disabled:opacity-50">
                    {{ $t('arcadia.reader.quick_check.check') }}
                </button>
                <template v-else-if="outcome.passed">
                    <p class="m-0 flex items-center gap-1.5 text-sm font-semibold text-guide-done">
                        <Check :size="16" aria-hidden="true" />
                        {{ $t('arcadia.reader.quick_check.right') }}
                    </p>
                    <button type="button" @click="emit('next')"
                            class="rounded-full bg-guide-accent px-5 py-2 text-sm font-semibold text-guide-on-accent">
                        {{ $t('arcadia.reader.quick_check.next') }}
                    </button>
                </template>
                <template v-else>
                    <p class="m-0 text-sm text-guide-ink-soft">{{ $t('arcadia.reader.quick_check.wrong') }}</p>
                    <button type="button" @click="retry"
                            class="rounded-full border border-guide-rule px-5 py-2 text-sm font-semibold text-guide-ink hover:border-guide-accent">
                        {{ $t('arcadia.reader.quick_check.retry') }}
                    </button>
                </template>
            </div>
        </template>
    </div>
</template>
