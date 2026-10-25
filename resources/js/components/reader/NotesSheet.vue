<script setup>
/**
 * The reader's notes on one line or stage, on a phone: a sheet from the bottom of the screen with
 * that target's notes and the box to add one, opened from the buttons beside a video or a stage
 * (`notes.open`). On a wide screen the side panel shows them instead, and this stays shut.
 */
import { computed, inject } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import NoteThread from './NoteThread.vue'

const props = defineProps({
    // Off when the side panel is showing.
    active: { type: Boolean, default: true },
    // What the open target is called: a line's or a stage's title, by key.
    titleFor: { type: Function, required: true },
    // The page's tokens, so the sheet is painted like the guide (it renders outside it).
    tokens: { type: Object, default: () => ({}) },
})

const notes = inject('guideNotes')

const open = computed({
    get: () => props.active && notes.opened.value !== null,
    set: value => { if (!value) notes.close() },
})
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="notes.opened.value" :style="tokens"
                       class="top-auto bottom-0 left-0 max-h-[85vh] w-full max-w-none translate-x-0 translate-y-0 rounded-t-2xl rounded-b-none border-guide-rule bg-guide-surface p-5 sm:left-1/2 sm:max-w-lg sm:-translate-x-1/2">
            <DialogHeader class="text-left">
                <DialogTitle class="text-guide-ink">{{ $t('arcadia.reader.notes.title') }}</DialogTitle>
                <DialogDescription class="text-guide-ink-soft">{{ titleFor(notes.opened.value) }}</DialogDescription>
            </DialogHeader>
            <NoteThread :target="notes.opened.value" autofocus :key="notes.idOf(notes.opened.value) + notes.openedAt.value" />
        </DialogContent>
    </Dialog>
</template>
