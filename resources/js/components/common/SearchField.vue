<script setup>
/**
 * A list's search box that searches when asked — Enter or its button — never while the words are
 * being typed. Its native clear (the × in a search input) asks too, so the full list comes back.
 *
 * Enter on an empty box fires both the submit and the native `search` event, so `search` can arrive
 * twice with the same words: the caller skips a term it already applied.
 *
 * The labels are props, as in CopyButton: each surface's lang file owns its own wording.
 */
import { Search } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

const term = defineModel({ type: String, default: '' })

defineProps({
    label: { type: String, required: true },
    buttonLabel: { type: String, required: true },
})

const emit = defineEmits(['search'])

const submit = () => emit('search', term.value.trim())
</script>

<template>
    <form role="search" class="flex items-center gap-2" @submit.prevent="submit">
        <Input v-model="term" type="search" class="min-w-0 flex-1"
               :placeholder="label" :aria-label="label"
               @search="event => !event.target.value && submit()" />
        <Button type="submit" variant="outline" size="icon" :aria-label="buttonLabel" :title="buttonLabel">
            <Search />
        </Button>
    </form>
</template>
