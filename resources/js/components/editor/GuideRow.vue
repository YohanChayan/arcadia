<script setup>
/**
 * One guide on the guides list, as a row: its tick for bulk actions, a small cover, what the
 * guide is and its state, its counts, and every action at the end — each in its own colour:
 * publish green, delete red, edit outlined as before, the screens it leads to quiet.
 *
 * Publishing mirrors `PublishGuide::assess`: offered while there is something to publish, shown
 * disabled when the guide has no content at all, and held invisible once it is published and up
 * to date, so the actions keep their columns from row to row.
 */
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Pencil, Trash2, ListTree, Link2, Users, Image as ImageIcon, Send } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    levelLabel: { type: String, required: true },
    status: { type: Object, required: true },
})

const emit = defineEmits(['toggle', 'link', 'publish', 'delete'])

const editorRoute = useEditorRoute()

// Nothing released and nothing in the draft: there is nothing to publish.
const hasNoContent = computed(() => !props.guide.has_release && !props.guide.has_unreleased_content)
const isUpToDate = computed(() => props.guide.status === 'published' && props.guide.has_release && !props.guide.has_unreleased_content)

// One line under the title when the guide's state needs saying; the first that applies.
const stateNote = computed(() => {
    if (hasNoContent.value) return { key: 'no_content', tone: 'text-ink-4' }
    if (props.guide.has_unreleased_content) return { key: 'unreleased_content', tone: 'text-warning' }
    if (props.guide.status === 'published' && !props.guide.has_release) return { key: 'no_release', tone: 'text-warning' }
    if (props.guide.status === 'draft' && props.guide.has_release) return { key: 'released_draft', tone: 'text-ink-4' }
    return null
})

const metrics = computed(() => [
    { key: 'readers', value: props.guide.readers_count },
    { key: 'goals', value: props.guide.goals?.length ?? 0 },
    { key: 'versions', value: props.guide.versions_count },
    { key: 'released', value: props.guide.released_versions_count, tone: props.guide.released_versions_count ? 'text-success' : 'text-ink-4' },
])

const presentation = computed(() => [
    { key: 'visits', value: props.guide.presentation.visits, tone: 'text-ink' },
    { key: 'requests', value: props.guide.presentation.requests, tone: 'text-ink' },
    { key: 'enrollments', value: props.guide.presentation.enrollments, tone: 'text-success' },
])

const screens = computed(() => [
    { key: 'content', href: editorRoute('content.show', { guide: props.guide.id }), icon: ListTree, label: trans('arcadia.editor.content.action') },
    { key: 'readers', href: editorRoute('readers.index', { guide: props.guide.id }), icon: Users, label: trans('arcadia.editor.readers.action') },
    { key: 'images', href: editorRoute('images.index', { guide: props.guide.id }), icon: ImageIcon, label: trans('arcadia.editor.images.action') },
])

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
</script>

<template>
    <li class="flex flex-col gap-3 rounded-xl border bg-card p-3 transition-colors sm:flex-row sm:items-center"
        :class="selected ? 'border-accent/50 bg-accent/5' : 'border-rule'">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <Checkbox :model-value="selected" @update:model-value="emit('toggle')"
                      :aria-label="$t('arcadia.editor.guides.bulk.select_guide', { title: guide.title })" />

            <!-- The cover, small; it leads to the guide's images, as the card's did. -->
            <Link :href="editorRoute('images.index', { guide: guide.id })"
                  class="relative grid aspect-[1200/630] w-28 shrink-0 place-items-center overflow-hidden rounded-md border border-rule bg-raised text-ink-4 no-underline sm:w-36"
                  :aria-label="$t('arcadia.editor.images.action')" :title="$t('arcadia.editor.images.action')">
                <img v-if="guide.cover" :src="guide.cover" alt="" width="1200" height="630" loading="lazy" class="absolute inset-0 size-full object-cover">
                <ImageIcon v-else :size="20" aria-hidden="true" />
            </Link>

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                    <Badge :variant="status.tone" class="shrink-0">{{ status.label }}</Badge>
                    <p class="m-0 truncate font-mono text-[11px] uppercase tracking-widest text-ink-4">
                        {{ guide.category?.name ?? '—' }} · {{ levelLabel }}
                    </p>
                </div>
                <Link :href="editorRoute('content.show', { guide: guide.id })" :title="guide.title"
                      class="mt-1 line-clamp-2 font-display text-sm font-semibold leading-snug text-ink no-underline hover:underline">
                    {{ guide.title }}
                </Link>
                <p v-if="stateNote" class="m-0 mt-0.5 text-xs" :class="stateNote.tone">
                    {{ $t(`arcadia.editor.guides.state.${stateNote.key}`) }}
                </p>

                <div class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-[11px] text-ink-4">
                    <span v-for="metric in metrics" :key="metric.key">
                        {{ $t(`arcadia.editor.guides.columns.${metric.key}`) }}
                        <span class="font-mono text-xs tabular-nums" :class="metric.tone ?? 'text-ink'">{{ metric.value }}</span>
                    </span>
                    <span class="flex flex-wrap items-baseline gap-x-2">
                        <span>{{ $t('arcadia.editor.guides.presentation') }}</span>
                        <span v-for="figure in presentation" :key="figure.key">
                            <span class="font-mono tabular-nums" :class="figure.tone">{{ figure.value }}</span>
                            {{ $t(`arcadia.editor.home.presentations.${figure.key}`).toLowerCase() }}
                        </span>
                        <span v-if="!guide.open_for_enrollment">· {{ $t('arcadia.editor.home.presentations.closed') }}</span>
                    </span>
                    <span class="font-mono"
                          :title="`${$t('arcadia.editor.guides.columns.created')} ${formatDate(guide.created_at)} · ${$t('arcadia.editor.guides.columns.updated')} ${formatDate(guide.updated_at)}`">
                        {{ $t('arcadia.editor.guides.columns.updated') }} {{ formatDate(guide.updated_at) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Every action at the end: the screens it leads to, then its verbs in their colours. -->
        <div class="flex shrink-0 items-center gap-1 border-t border-rule pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3">
            <Link v-for="screen in screens" :key="screen.key" :href="screen.href"
                  :class="buttonVariants({ variant: 'ghost', size: 'icon-sm' })" class="no-underline"
                  :aria-label="screen.label" :title="screen.label">
                <component :is="screen.icon" :size="16" aria-hidden="true" />
            </Link>
            <Button type="button" variant="ghost" size="icon-sm" @click="emit('link')" :class="guide.readable ? 'text-accent' : 'text-ink-4'"
                    :aria-label="$t('arcadia.editor.guides.link.action')" :title="$t('arcadia.editor.guides.link.action')">
                <Link2 :size="16" aria-hidden="true" />
            </Button>

            <span class="mx-1 h-5 w-px bg-rule" aria-hidden="true" />

            <!-- A disabled button shows no tooltip, so the reason sits on its wrapper. -->
            <span :class="{ invisible: isUpToDate }" :title="hasNoContent ? $t('arcadia.editor.guides.publish.no_content') : $t('arcadia.editor.guides.publish.action')">
                <Button type="button" variant="success" size="icon-sm" :disabled="hasNoContent || isUpToDate" @click="emit('publish')"
                        :aria-label="hasNoContent ? $t('arcadia.editor.guides.publish.no_content') : $t('arcadia.editor.guides.publish.action')">
                    <Send :size="16" aria-hidden="true" />
                </Button>
            </span>
            <Link :href="editorRoute('guides.edit', guide.id)"
                  :class="buttonVariants({ variant: 'outline', size: 'icon-sm' })" class="no-underline"
                  :aria-label="$t('arcadia.editor.guides.edit')" :title="$t('arcadia.editor.guides.edit')">
                <Pencil :size="14" aria-hidden="true" />
            </Link>
            <Button type="button" variant="danger" size="icon-sm" @click="emit('delete')"
                    :aria-label="$t('arcadia.editor.guides.delete.action')" :title="$t('arcadia.editor.guides.delete.action')">
                <Trash2 :size="16" aria-hidden="true" />
            </Button>
        </div>
    </li>
</template>
