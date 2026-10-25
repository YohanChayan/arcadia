<script setup>
/**
 * One line's or one stage's notes, as their reader wrote them, oldest first — each one to change
 * or delete in place — and the box to add another. Shown in the side panel on a wide screen and
 * in the sheet on a phone; the notes themselves live in `useGuideNotes`, provided by the page.
 *
 * In the guide's own tokens: it is part of the reader's page.
 */
import { inject, nextTick, onMounted, ref } from 'vue'
import { Pencil, Trash2 } from '@lucide/vue'

const props = defineProps({
    // { kind: 'item' | 'stage', key }
    target: { type: Object, required: true },
    // Put the cursor in the new-note box when shown — when the reader asked to add one.
    autofocus: { type: Boolean, default: false },
})

const notes = inject('guideNotes')

const draft = ref('')
const saving = ref(false)
const box = ref(null)

const add = async () => {
    const body = draft.value.trim()
    if (!body || saving.value) return

    saving.value = true
    if (await notes.add(props.target, body)) draft.value = ''
    saving.value = false
}

// The note being changed, and its text so far.
const editingId = ref(null)
const editing = ref('')

const edit = note => {
    editingId.value = note.id
    editing.value = note.body
}

const saveEdit = async note => {
    const body = editing.value.trim()
    if (!body) return
    if (await notes.update(note, body)) editingId.value = null
}

// A delete asks once, in place.
const confirmingId = ref(null)

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })

onMounted(async () => {
    if (props.autofocus) {
        await nextTick()
        // Without scrolling: the box may be in the side panel, and moving the page would lose the reader's place.
        box.value?.focus({ preventScroll: true })
    }
})
</script>

<template>
    <div>
        <ul v-if="notes.forTarget(target).length" class="m-0 flex flex-col gap-2 p-0">
            <li v-for="note in notes.forTarget(target)" :key="note.id" class="list-none rounded-lg bg-guide-well px-3 py-2.5">
                <template v-if="editingId === note.id">
                    <textarea v-model="editing" rows="3" :maxlength="notes.maxLength" :aria-label="$t('arcadia.reader.notes.edit')"
                              class="w-full resize-y rounded-md border border-guide-rule bg-guide-surface p-2 text-sm text-guide-ink outline-none focus:border-guide-accent"></textarea>
                    <div class="mt-1.5 flex justify-end gap-2 text-xs">
                        <button type="button" class="text-guide-ink-soft hover:text-guide-ink" @click="editingId = null">{{ $t('arcadia.reader.notes.cancel') }}</button>
                        <button type="button" class="font-semibold text-guide-accent" @click="saveEdit(note)">{{ $t('arcadia.reader.notes.save') }}</button>
                    </div>
                </template>
                <template v-else>
                    <p class="m-0 text-sm whitespace-pre-line text-guide-ink">{{ note.body }}</p>
                    <div class="mt-1.5 flex items-center justify-between gap-2 text-xs text-guide-ink-soft">
                        <span>{{ formatDate(note.updated_at) }}</span>
                        <span v-if="confirmingId === note.id" class="flex gap-3">
                            <button type="button" class="hover:text-guide-ink" @click="confirmingId = null">{{ $t('arcadia.reader.notes.cancel') }}</button>
                            <button type="button" class="font-semibold text-guide-accent" @click="notes.remove(note); confirmingId = null">{{ $t('arcadia.reader.notes.confirm_delete') }}</button>
                        </span>
                        <span v-else class="flex gap-1">
                            <button type="button" class="rounded p-1 hover:text-guide-accent" :aria-label="$t('arcadia.reader.notes.edit')" @click="edit(note)">
                                <Pencil :size="13" aria-hidden="true" />
                            </button>
                            <button type="button" class="rounded p-1 hover:text-guide-accent" :aria-label="$t('arcadia.reader.notes.delete')" @click="confirmingId = note.id">
                                <Trash2 :size="13" aria-hidden="true" />
                            </button>
                        </span>
                    </div>
                </template>
            </li>
        </ul>

        <form class="mt-2" @submit.prevent="add">
            <textarea ref="box" v-model="draft" rows="2" :maxlength="notes.maxLength"
                      :placeholder="$t('arcadia.reader.notes.placeholder')" :aria-label="$t('arcadia.reader.notes.add')"
                      class="w-full resize-y rounded-md border border-guide-rule bg-guide-surface p-2 text-sm text-guide-ink outline-none placeholder:text-guide-ink-soft focus:border-guide-accent"
                      @keydown.meta.enter.prevent="add" @keydown.ctrl.enter.prevent="add"></textarea>
            <div class="mt-1 flex justify-end">
                <button type="submit" :disabled="!draft.trim() || saving"
                        class="rounded-full bg-guide-accent px-3 py-1.5 text-xs font-semibold text-guide-on-accent disabled:opacity-40">
                    {{ $t('arcadia.reader.notes.add') }}
                </button>
            </div>
        </form>
    </div>
</template>
