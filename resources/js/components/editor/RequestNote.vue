<script setup>
/**
 * The owner's own note on an enrollment request — his record of the payment arranged outside
 * Arcadia. Shown as a line that opens into a box; blank clears it. The requester never sees it.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { NotebookPen } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
    note: { type: String, default: null },
    saveUrl: { type: String, required: true },
    max: { type: Number, default: 1000 },
})

const editing = ref(false)
const draft = ref('')

const start = () => {
    draft.value = props.note ?? ''
    editing.value = true
}

const save = () => router.put(props.saveUrl, { owner_note: draft.value }, {
    preserveScroll: true,
    onSuccess: () => { editing.value = false },
})
</script>

<template>
    <div v-if="editing" class="flex flex-col gap-2">
        <Textarea v-model="draft" rows="2" :maxlength="max" :placeholder="$t('arcadia.editor.readers.requests.note_placeholder')"
                  :aria-label="$t('arcadia.editor.readers.requests.note')" />
        <div class="flex gap-2">
            <Button type="button" variant="outline" size="sm" @click="save">{{ $t('arcadia.editor.readers.requests.note_save') }}</Button>
            <Button type="button" variant="ghost" size="sm" @click="editing = false">{{ $t('arcadia.editor.readers.requests.note_cancel') }}</Button>
        </div>
    </div>
    <button v-else type="button" class="inline-flex items-center gap-1.5 text-left text-xs text-ink-3 hover:text-accent" @click="start">
        <NotebookPen :size="12" class="shrink-0" aria-hidden="true" />
        {{ note || $t('arcadia.editor.readers.requests.note_add') }}
    </button>
</template>
