<script setup>
/**
 * One reader's guides in this language, from the readers list: each with its status, when it was
 * given to them, and a link to that guide's readers screen, where they are managed. For a reader
 * who has not signed in yet, whether the invitation email went.
 */
import { BookOpen, UserRound } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // The reader shown, or null while closed: { email, name, has_account, guides: [{ id, title, status, assigned_at }] }.
    reader: { type: Object, default: null },
    // [{ value, label, tone }]
    statuses: { type: Array, required: true },
})

const emit = defineEmits(['close'])
const editorRoute = useEditorRoute()

const statusFor = value => props.statuses.find(s => s.value === value) ?? { label: value, tone: 'neutral' }

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
</script>

<template>
    <Dialog :open="reader !== null" @update:open="value => !value && emit('close')">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserRound :size="18" aria-hidden="true" />
                    {{ reader?.name || reader?.email }}
                </DialogTitle>
                <DialogDescription>
                    <span v-if="reader?.name">{{ reader.email }} · </span>
                    {{ $tChoice('arcadia.editor.reader_directory.guides_count', reader?.guides.length ?? 0, { count: reader?.guides.length ?? 0 }) }}
                    <span v-if="reader && !reader.has_account"> · {{ $t('arcadia.editor.reader_directory.account.no') }}</span>
                </DialogDescription>
            </DialogHeader>

            <ul class="m-0 max-h-[60vh] divide-y divide-rule overflow-y-auto p-0">
                <li v-for="guide in reader?.guides ?? []" :key="guide.id" class="flex list-none items-center gap-3 py-2.5">
                    <BookOpen :size="16" class="shrink-0 text-ink-4" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <Link :href="editorRoute('readers.index', { guide: guide.id })"
                              class="block truncate text-sm font-medium text-ink no-underline hover:text-accent hover:underline">
                            {{ guide.title }}
                        </Link>
                        <p class="m-0 font-mono text-[11px] text-ink-4">
                            {{ $t('arcadia.editor.reader_directory.assigned', { date: formatDate(guide.assigned_at) }) }}
                            <template v-if="!reader.has_account">
                                · {{ guide.notified_at
                                    ? $t('arcadia.editor.reader_directory.invited', { date: formatDate(guide.notified_at) })
                                    : $t('arcadia.editor.reader_directory.not_invited') }}
                            </template>
                        </p>
                    </div>
                    <Badge :variant="statusFor(guide.status).tone">{{ statusFor(guide.status).label }}</Badge>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
