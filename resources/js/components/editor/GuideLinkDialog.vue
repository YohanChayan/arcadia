<script setup>
/**
 * A guide's link to share, from its row in the guides list: copy it, see whether a reader could
 * open it yet, and change it. Changing it is confirmed in place — the old link dies at once —
 * rather than in a second dialog over this one.
 *
 * Moved here from the guide's edit screen on 2026-10-14: sharing is the thing done most often,
 * and the list is where the owner already is.
 */
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { RefreshCw } from '@lucide/vue'
import CodeBlock from '@/components/common/CodeBlock.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // The row: id, title, link, readable, status. Null closes the dialog.
    guide: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const editorRoute = useEditorRoute()

const confirming = ref(false)
const rotating = ref(false)

watch(() => props.guide?.id, () => { confirming.value = false })

// Why a reader cannot open it yet: publishing comes first, then a released version.
const state = computed(() => {
    if (!props.guide || props.guide.readable) return { tone: 'ok', key: 'ready' }

    return props.guide.status === 'published'
        ? { tone: 'pending', key: 'not_released' }
        : { tone: 'pending', key: 'not_published' }
})

const rotate = () => {
    rotating.value = true
    router.post(editorRoute('guides.rotate-link', { guide: props.guide.id }), {}, {
        preserveScroll: true,
        // The list reloads with the new link; the dialog reads it from the row.
        onFinish: () => {
            rotating.value = false
            confirming.value = false
        },
    })
}
</script>

<template>
    <Dialog :open="guide !== null" @update:open="value => !value && emit('close')">
        <DialogContent v-if="guide" class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('arcadia.editor.guides.link.title') }}</DialogTitle>
                <DialogDescription>{{ guide.title }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-3">
                <p class="m-0 text-sm text-ink-3">{{ $t('arcadia.editor.guides.link.hint') }}</p>
                <CodeBlock :code="guide.link" :copy-label="$t('arcadia.editor.guides.link.copy')" :copied-label="$t('arcadia.editor.guides.link.copied')" />
                <Badge :variant="state.tone" class="justify-self-start">{{ $t(`arcadia.editor.guides.link.${state.key}`) }}</Badge>
            </div>

            <div v-if="confirming" class="rounded-control border border-danger/30 bg-danger/5 p-4">
                <p class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.guides.link.rotate_title') }}</p>
                <p class="mt-1 mb-3 text-sm text-ink-3">{{ $t('arcadia.editor.guides.link.rotate_body') }}</p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" :disabled="rotating" @click="confirming = false">{{ $t('arcadia.editor.cancel') }}</Button>
                    <Button type="button" variant="destructive" size="sm" :disabled="rotating" @click="rotate">
                        <RefreshCw :size="14" aria-hidden="true" :class="rotating ? 'animate-spin' : ''" />
                        {{ $t('arcadia.editor.guides.link.rotate') }}
                    </Button>
                </div>
            </div>

            <DialogFooter>
                <Button v-if="!confirming" type="button" variant="outline" class="sm:mr-auto" @click="confirming = true">
                    <RefreshCw :size="14" aria-hidden="true" />
                    {{ $t('arcadia.editor.guides.link.rotate') }}
                </Button>
                <Button type="button" variant="brand" @click="emit('close')">{{ $t('arcadia.editor.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
