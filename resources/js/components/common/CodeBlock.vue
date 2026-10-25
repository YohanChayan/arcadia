<script setup>
/**
 * A block of code or a token, shown as written, with a copy button.
 *
 * Built for an API or MCP console: request samples, keys, endpoint URLs. There is no syntax
 * highlighting on purpose — it would be a new dependency for a screen that shows a few lines
 * at a time, and a key or a URL has no syntax to highlight.
 */
import CopyButton from '@/components/common/CopyButton.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'

defineProps({
    code: { type: String, required: true },
    caption: { type: String, default: '' },
    copyLabel: { type: String, required: true },
    copiedLabel: { type: String, default: '' },
})
</script>

<template>
    <figure class="m-0 min-w-0 max-w-full rounded-control border border-rule bg-well">
        <figcaption v-if="caption" class="flex items-center justify-between gap-2 border-b border-rule px-3 py-1.5">
            <Eyebrow tone="muted" size="sm" as="span">{{ caption }}</Eyebrow>
            <CopyButton :value="code" :label="copyLabel" :copied-label="copiedLabel" />
        </figcaption>
        <div class="relative">
            <pre class="m-0 overflow-x-auto px-3 py-2.5 pr-11 font-mono text-sm text-ink"><code>{{ code }}</code></pre>
            <CopyButton
                v-if="!caption"
                :value="code"
                :label="copyLabel"
                :copied-label="copiedLabel"
                class="absolute right-1.5 top-1.5"
            />
        </div>
    </figure>
</template>
