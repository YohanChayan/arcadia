<script setup>
/**
 * "Importar respuesta": one door for every AI answer brought into a guide's draft. The owner picks
 * what the answer is — the guide's content (`ContentImportForm`, from the research prompt) or
 * activities (`ActivityImportForm`, from an activity prompt) — and gets that kind's own form, with
 * its own check, options and endpoint. The two never share a form: they are different writes.
 *
 * A file that looks like the other kind says so, with the way to switch; the guess decides nothing.
 * Picking a kind starts its form afresh.
 *
 * With no draft — the latest version released — there is nothing to import into: it says so, and
 * offers to draft the next version, which copies the released one with every key kept.
 */
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { CopyPlus, Info } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useEditorRoute } from '@/composables/useEditorRoute.js'
import ContentImportForm from './ContentImportForm.vue'
import ActivityImportForm from './ActivityImportForm.vue'

const props = defineProps({
    guideId: { type: Number, required: true },
    // Whether the latest version is a draft to import into.
    editable: { type: Boolean, required: true },
})

const open = defineModel('open', { type: Boolean, default: false })

const editorRoute = useEditorRoute()

const KINDS = ['content', 'activities']
const kind = ref('content')
// The kind the chosen file looks like, when it is not the one picked.
const looksLike = ref(null)
// Bumped to start a form afresh.
const round = ref(0)

const pick = next => {
    kind.value = next
    looksLike.value = null
    round.value++
}

watch(open, isOpen => {
    if (isOpen) {
        looksLike.value = null
        round.value++
    }
})

const sniffed = detected => {
    looksLike.value = detected && detected !== kind.value ? detected : null
}

const drafting = ref(false)
const draftNext = () => {
    drafting.value = true
    router.post(editorRoute('versions.store', { guide: props.guideId }), {}, {
        preserveScroll: true,
        onFinish: () => { drafting.value = false },
    })
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-[calc(100%-2rem)] max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.answer_import.title') }}</DialogTitle>
                <DialogDescription>{{ $t('arcadia.editor.answer_import.lead') }}</DialogDescription>
            </DialogHeader>

            <!-- Nothing to import into until there is a draft. -->
            <div v-if="!editable" class="flex flex-col items-start gap-3 rounded-control border border-rule bg-well p-4 text-sm text-ink-2">
                <p class="m-0 flex items-start gap-2">
                    <Info :size="16" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
                    {{ $t('arcadia.editor.answer_import.no_draft') }}
                </p>
                <Button type="button" variant="brand" size="sm" :disabled="drafting" @click="draftNext">
                    <CopyPlus :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.versions.draft_next') }}
                </Button>
            </div>

            <template v-else>
                <div role="radiogroup" :aria-label="$t('arcadia.editor.answer_import.kind')" class="inline-flex w-fit rounded-full border border-rule p-1">
                    <button v-for="option in KINDS" :key="option" type="button" role="radio" :aria-checked="kind === option"
                            class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                            :class="kind === option ? 'bg-accent text-on-accent' : 'text-ink-2 hover:text-ink'"
                            @click="pick(option)">
                        {{ $t(`arcadia.editor.answer_import.kinds.${option}`) }}
                    </button>
                </div>

                <p v-if="looksLike" class="m-0 flex flex-wrap items-center gap-2 rounded-control border border-warning/40 bg-warning/5 px-3 py-2 text-sm text-ink-2">
                    {{ $t('arcadia.editor.answer_import.looks_like', { kind: $t(`arcadia.editor.answer_import.kinds.${looksLike}`) }) }}
                    <button type="button" class="font-semibold text-accent hover:underline" @click="pick(looksLike)">
                        {{ $t('arcadia.editor.answer_import.switch') }}
                    </button>
                </p>

                <ContentImportForm v-if="kind === 'content'" :key="`content-${round}`" :guide-id="guideId" @kind="sniffed" @done="open = false" />
                <ActivityImportForm v-else :key="`activities-${round}`" :guide-id="guideId" @kind="sniffed" @done="open = false" />
            </template>
        </DialogContent>
    </Dialog>
</template>
