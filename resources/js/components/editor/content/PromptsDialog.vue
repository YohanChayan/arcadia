<script setup>
/**
 * "Prompts": every prompt a guide is worked with, filled from it, in one place — the research
 * prompt that writes its content, and each activity prompt there is (`activityPrompts`), its
 * content put in by the server. A list to pick from, and the chosen one filled beside it, to copy
 * into ChatGPT or Gemini and bring the answer back with "Importar respuesta".
 *
 * Each keeps its own way: copying the research prompt records it on the guide (`copied`, as
 * before); copying an activity prompt records nothing. An activity prompt added later appears
 * here with no change to this screen.
 */
import { computed, ref, watch } from 'vue'
import { CircleCheck } from '@lucide/vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import VersionedPromptFiller from '@/components/editor/prompts/VersionedPromptFiller.vue'

const props = defineProps({
    guideId: { type: Number, required: true },
    // { prompt, versionId, values } — the research prompt, as the content screen has it.
    research: { type: Object, required: true },
    // [{ family, label, prompt, values }]
    activityPrompts: { type: Array, default: () => [] },
    choices: { type: Object, required: true },
    // What recording the last research copy said.
    researchRecorded: { type: Boolean, default: false },
    researchError: { type: String, default: '' },
})

const emit = defineEmits(['copied-research'])

const open = defineModel('open', { type: Boolean, default: false })

const RESEARCH = 'research'
const chosen = ref(RESEARCH)

watch(open, isOpen => {
    if (isOpen) chosen.value = RESEARCH
})

const activity = computed(() => props.activityPrompts.find(item => item.family === chosen.value) ?? null)

const item = 'w-full rounded-control px-3 py-2 text-left text-sm transition-colors'
const tone = key => chosen.value === key ? 'bg-accent/15 font-semibold text-ink' : 'text-ink-2 hover:bg-well hover:text-ink'
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-[calc(100%-2rem)] max-w-6xl">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.prompts_hub.title') }}</DialogTitle>
                <DialogDescription>{{ $t('arcadia.editor.prompts_hub.lead') }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-5 md:grid-cols-[13rem_minmax(0,1fr)]">
                <nav class="flex flex-row flex-wrap gap-1 md:flex-col" :aria-label="$t('arcadia.editor.prompts_hub.title')">
                    <Eyebrow as="span" tone="muted" size="sm" class="hidden px-3 pt-1 md:block">{{ $t('arcadia.editor.prompts_hub.content') }}</Eyebrow>
                    <button type="button" :class="[item, tone(RESEARCH)]" :aria-current="chosen === RESEARCH || undefined" @click="chosen = RESEARCH">
                        {{ $t('arcadia.editor.content.research.action') }}
                    </button>
                    <template v-if="activityPrompts.length">
                        <Eyebrow as="span" tone="muted" size="sm" class="hidden px-3 pt-3 md:block">{{ $t('arcadia.editor.prompts_hub.activities') }}</Eyebrow>
                        <button v-for="prompt in activityPrompts" :key="prompt.family" type="button" :class="[item, tone(prompt.family)]"
                                :aria-current="chosen === prompt.family || undefined" @click="chosen = prompt.family">
                            {{ prompt.label }}
                        </button>
                    </template>
                </nav>

                <div class="min-w-0">
                    <!-- The research prompt: copying it records it on the guide. -->
                    <template v-if="chosen === RESEARCH">
                        <p class="mt-0 mb-3 text-sm text-ink-3">{{ research.prompt ? $t('arcadia.editor.content.research.hint') : $t('arcadia.editor.content.research.missing') }}</p>
                        <p v-if="researchRecorded" class="mt-0 mb-3 flex items-center gap-1.5 text-sm text-success">
                            <CircleCheck :size="15" aria-hidden="true" />
                            {{ $t('arcadia.editor.content.research.recorded') }}
                        </p>
                        <p v-if="researchError" class="mt-0 mb-3 text-sm text-danger">{{ researchError }}</p>
                        <VersionedPromptFiller v-if="research.prompt" :key="RESEARCH" :prompt="research.prompt" :version-id="research.versionId"
                                               :choices="choices" :initial="research.values" @copied="payload => emit('copied-research', payload)" />
                    </template>

                    <!-- An activity prompt: the guide's content put in by the server; copying records nothing. -->
                    <template v-else-if="activity">
                        <p class="mt-0 mb-3 text-sm text-ink-3">{{ $t('arcadia.editor.activity_import.prompt_hint') }}</p>
                        <VersionedPromptFiller :key="activity.family" :prompt="activity.prompt" :choices="choices"
                                               :initial="activity.values" :guide-id="guideId" />
                    </template>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
