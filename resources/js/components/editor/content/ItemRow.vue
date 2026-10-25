<script setup>
/**
 * One checkable line. Its key is shown small: it is the line's identity across versions, and
 * what a dead link is repaired by — "fix in every version", offered on released versions too.
 *
 * A link with a brief — what it teaches, from the research — shows it under a toggle, with the
 * way to a replacement (`ItemBrief`); a line whose source has gone shows it open. A clipped
 * video shows its clip and the evidence for it, which is what the owner reviews.
 */
import { computed, inject, ref } from 'vue'
import { ChevronDown, ExternalLink, Wrench } from '@lucide/vue'
import ItemBrief from './ItemBrief.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import NodeForm from './NodeForm.vue'
import NodeToolbar from './NodeToolbar.vue'
import { clipLabel as describeClip } from '@/lib/clock.js'

const props = defineProps({
    item: { type: Object, required: true },
    first: Boolean,
    last: Boolean,
})

const content = inject('content')
const editing = ref(false)
const isLink = computed(() => content.linkKinds.includes(props.item.kind))

const hasBrief = computed(() => isLink.value && Boolean(props.item.brief))
const clipLabel = computed(() => props.item.kind === 'youtube_video' ? describeClip(props.item.clip_start, props.item.clip_end) : null)

// Open by itself when the source has gone: that is when it is needed.
const showBrief = ref(!props.item.is_available)
</script>

<template>
    <NodeForm v-if="editing" node="items" :row="item" @done="editing = false" />
    <div v-else class="py-2">
        <div class="flex items-start gap-3" :class="item.is_available ? '' : 'opacity-60'">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-sm font-medium text-ink">{{ item.title }}</span>
                    <Badge variant="neutral">{{ content.label('items', item.kind) }}</Badge>
                    <Badge v-if="item.is_preview" variant="active">{{ $t('arcadia.editor.content.flags.preview') }}</Badge>
                    <Badge v-if="!item.is_available" variant="off">{{ $t('arcadia.editor.content.flags.unavailable') }}</Badge>
                </div>
                <a v-if="isLink && item.url" :href="item.url" target="_blank" rel="noopener noreferrer"
                   class="mt-0.5 inline-flex max-w-full items-center gap-1 truncate text-xs text-ink-3 no-underline hover:text-accent"
                   :aria-label="`${$t('arcadia.editor.content.actions.open')}: ${item.title}`">
                    <ExternalLink :size="12" aria-hidden="true" />
                    <span class="truncate">{{ item.url }}</span>
                </a>
                <p v-if="clipLabel" class="mt-0.5 mb-0 font-mono text-[11px] text-ink-2">{{ clipLabel }}</p>
                <p v-if="clipLabel && item.clip_evidence" class="mt-0.5 mb-0 text-xs text-ink-3 italic">{{ item.clip_evidence }}</p>
                <p v-if="item.hint" class="mt-0.5 mb-0 text-xs text-ink-4">{{ item.hint }}</p>
                <p v-if="item.body" class="mt-1 mb-0 whitespace-pre-line text-sm text-ink-2">{{ item.body }}</p>
            </div>
            <span class="hidden pt-0.5 font-mono text-[11px] text-ink-4 sm:inline">{{ item.key }}</span>
            <Button type="button" variant="ghost" size="icon-sm" @click="content.fix(item)" :aria-label="$t('arcadia.editor.versions.fix.action')" :title="$t('arcadia.editor.versions.fix.action')">
                <Wrench :size="14" aria-hidden="true" />
            </Button>
            <NodeToolbar node="items" :row="item" :first="first" :last="last" @edit="editing = true" />
        </div>

        <!-- Not dimmed with the line: when the source has gone, this is what matters. -->
        <div v-if="hasBrief" class="mt-1.5">
            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-ink-3 hover:text-accent"
                    :aria-expanded="showBrief" @click="showBrief = !showBrief">
                {{ $t('arcadia.editor.content.brief.toggle') }}
                <ChevronDown :size="12" class="transition-transform" :class="showBrief ? 'rotate-180' : ''" aria-hidden="true" />
            </button>
            <ItemBrief v-if="showBrief" :item="item" class="mt-1.5" />
        </div>
    </div>
</template>
