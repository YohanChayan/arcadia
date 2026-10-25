<script setup>
/**
 * A slot's images side by side, each whole as it is posted — the guide's flyers. Each card shows
 * the image at a post's width, its original's size and a link to it, and asks to be removed; the
 * removing, like the uploading, is the page's.
 */
import { ExternalLink, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'

defineProps({
    // [{ id, original, crops: { post }, width, height, bytes }], in the order they were added.
    images: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['remove'])

const formatSize = bytes => bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.round(bytes / 1024)} KB`
</script>

<template>
    <ul class="m-0 grid list-none grid-cols-2 items-start gap-5 p-0 sm:grid-cols-3 lg:grid-cols-4">
        <li v-for="(image, index) in images" :key="image.id">
            <figure class="m-0">
                <a :href="image.original" target="_blank" rel="noopener" class="block">
                    <img :src="image.crops.post" :alt="`${index + 1}`" loading="lazy"
                         class="block w-full rounded-control border border-rule">
                </a>
                <figcaption class="mt-1.5 flex items-start justify-between gap-2 text-xs text-ink-4">
                    <span class="min-w-0">
                        {{ image.width }} × {{ image.height }} px · {{ formatSize(image.bytes) }}
                        <a :href="image.original" target="_blank" rel="noopener"
                           class="mt-0.5 flex items-center gap-1 text-ink-3 no-underline hover:text-accent">
                            <ExternalLink :size="12" aria-hidden="true" />
                            {{ $t('arcadia.editor.images.download') }}
                        </a>
                    </span>
                    <Button type="button" variant="ghost-danger" size="icon" class="size-8 shrink-0" :disabled="disabled"
                            :aria-label="$t('arcadia.editor.images.remove.action')" :title="$t('arcadia.editor.images.remove.action')"
                            @click="emit('remove', image)">
                        <Trash2 :size="14" aria-hidden="true" />
                    </Button>
                </figcaption>
            </figure>
        </li>
    </ul>
</template>
