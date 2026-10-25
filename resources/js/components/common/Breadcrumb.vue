<script setup>
/**
 * Where a screen sits, root first — "Panel › Guías › Excel › Lectores" — every step but the last a
 * link, and a back arrow to the nearest one. Above a screen's `PageHeader`, never inside it.
 *
 * Long steps truncate rather than wrap the trail onto a second line; the full text stays on hover.
 * The labels are props, as in CopyButton: each surface's lang file owns its own wording.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, ChevronRight } from '@lucide/vue'

const props = defineProps({
    // [{ label, href }], root first. The last is this screen; its href, if any, is ignored.
    items: { type: Array, required: true },
    label: { type: String, required: true },
    backLabel: { type: String, required: true },
})

const parent = computed(() => props.items.slice(0, -1).findLast(item => item.href) ?? null)
</script>

<template>
    <nav :aria-label="label" class="mb-4 flex min-w-0 items-center gap-2">
        <Link v-if="parent" :href="parent.href" :aria-label="backLabel" :title="backLabel"
              class="inline-flex size-7 shrink-0 items-center justify-center rounded-control text-ink-3 no-underline transition-colors hover:bg-hover hover:text-ink">
            <ArrowLeft :size="15" aria-hidden="true" />
        </Link>
        <ol class="m-0 flex min-w-0 list-none items-center gap-1 p-0 text-sm">
            <li v-for="(item, index) in items" :key="index" class="flex min-w-0 items-center gap-1">
                <ChevronRight v-if="index > 0" :size="14" class="shrink-0 text-ink-4" aria-hidden="true" />
                <span v-if="index === items.length - 1" aria-current="page" :title="item.label"
                      class="truncate font-medium text-ink">{{ item.label }}</span>
                <Link v-else-if="item.href" :href="item.href" :title="item.label"
                      class="max-w-48 truncate text-ink-3 no-underline transition-colors hover:text-accent">{{ item.label }}</Link>
                <span v-else :title="item.label" class="max-w-48 truncate text-ink-3">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
