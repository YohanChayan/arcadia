<script setup>
/**
 * Copies a value to the clipboard and says so for two seconds.
 *
 * The labels are props rather than `$t()` keys because this component serves every surface,
 * and each surface's lang file already owns its own wording. `label` is required: an
 * icon-only button with no accessible name is invisible to a screen reader.
 */
import { onBeforeUnmount, ref } from 'vue'
import { Check, Copy } from '@lucide/vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true },
    copiedLabel: { type: String, default: '' },
})

const copied = ref(false)
let timer = null

async function copy() {
    try {
        await navigator.clipboard.writeText(props.value)
    } catch {
        // No clipboard permission, or an insecure origin. Nothing was copied, so say nothing.
        return
    }

    copied.value = true
    clearTimeout(timer)
    timer = setTimeout(() => { copied.value = false }, 2000)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        :aria-label="copied && copiedLabel ? copiedLabel : label"
        :title="copied && copiedLabel ? copiedLabel : label"
        @click="copy"
    >
        <Check v-if="copied" class="text-success" />
        <Copy v-else />
    </Button>
</template>
