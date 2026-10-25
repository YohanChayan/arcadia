<script setup>
/**
 * The owner sets colours — the guide's default for new readers, or one reader's own — with the
 * guide itself beside the picker, repainted on every pick. The preview is the editor's real
 * preview page in a frame (`?palette=&accent=`), which writes nothing; only Save does.
 *
 * Over colours the reader chose themselves, Save asks first, in place: the badge said "Reader",
 * and overwriting their choice is the one thing it is there to prevent.
 */
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { TriangleAlert } from '@lucide/vue'
import AppearancePicker from '@/components/AppearancePicker.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    options: { type: Object, required: true },
    // What is being painted: { reader: row } for one reader, { reader: null } for the default.
    // Null closes the dialog.
    target: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const editorRoute = useEditorRoute()

const palette = ref('arcadia')
const accent = ref('teal')
const confirming = ref(false)
const saving = ref(false)

const reader = computed(() => props.target?.reader ?? null)

watch(() => props.target, target => {
    if (!target) return
    const source = target.reader ?? props.guide
    palette.value = source.palette
    accent.value = source.accent
    confirming.value = false
})

const previewUrl = computed(() => editorRoute('preview', {
    guide: props.guide.id,
    ...(reader.value ? { reader: reader.value.id } : {}),
    palette: palette.value,
    accent: accent.value,
}))

const save = () => {
    if (reader.value?.appearance_by === 'reader' && !confirming.value) {
        confirming.value = true
        return
    }

    const url = reader.value
        ? editorRoute('appearance.reader', { guide: props.guide.id, reader: reader.value.id })
        : editorRoute('appearance.guide', { guide: props.guide.id })

    saving.value = true
    router.put(url, { palette: palette.value, accent: accent.value, overwrite: confirming.value }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Dialog :open="target !== null" @update:open="value => !value && emit('close')">
        <DialogContent v-if="target" class="sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ reader ? $t('arcadia.editor.appearance.reader_title') : $t('arcadia.editor.appearance.default_title') }}</DialogTitle>
                <DialogDescription>{{ reader ? reader.email : $t('arcadia.editor.appearance.default_hint') }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 md:grid-cols-[minmax(0,18rem)_1fr]">
                <AppearancePicker v-model:palette="palette" v-model:accent="accent" :options="options" name="editor-appearance" />

                <div class="min-w-0">
                    <p class="mt-0 mb-2 text-sm font-semibold text-ink">{{ $t('arcadia.editor.appearance.preview') }}</p>
                    <iframe :src="previewUrl" :title="$t('arcadia.editor.appearance.preview')"
                            class="h-[26rem] w-full rounded-control border border-rule bg-ground md:h-[32rem]"></iframe>
                </div>
            </div>

            <div v-if="confirming" class="flex gap-3 rounded-control border border-signal/30 bg-signal/5 p-4" role="alert">
                <TriangleAlert :size="18" class="mt-0.5 shrink-0 text-signal" aria-hidden="true" />
                <div>
                    <p class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.appearance.overwrite_title') }}</p>
                    <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.editor.appearance.overwrite_body') }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" :disabled="saving" @click="confirming ? (confirming = false) : emit('close')">{{ $t('arcadia.editor.cancel') }}</Button>
                <Button type="button" variant="brand" :disabled="saving" @click="save">
                    {{ confirming ? $t('arcadia.editor.appearance.overwrite_confirm') : $t('arcadia.editor.appearance.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
