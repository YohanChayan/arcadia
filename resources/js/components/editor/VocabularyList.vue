<script setup>
/**
 * One vocabulary — categories or topics — in this site's language, as the editor
 * lists a resource: a `DataTable`, add and rename in a dialog (`TermDialog`), delete behind a
 * confirmation. How many guides use a term is shown beside it, so a delete is never a surprise.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans, transChoice } from 'laravel-vue-i18n'
import { Plus, Pencil, Trash2 } from '@lucide/vue'
import DataTable from '@/components/common/DataTable.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { Button } from '@/components/ui/button'
import TermDialog from '@/components/editor/TermDialog.vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    vocabulary: { type: String, required: true },
    terms: { type: Array, required: true },
})

const editorRoute = useEditorRoute()

const columns = computed(() => [
    { key: 'name', label: trans('arcadia.editor.vocabulary.fields.name') },
    { key: 'used', label: '', align: 'right' },
    { key: 'actions', label: '', align: 'right', width: '1%' },
])

// `null` closed, `{}` adding, a term renaming.
const dialog = ref(null)

const pendingDelete = ref(null)
const deleting = ref(false)
const confirmDelete = () => {
    deleting.value = true
    router.delete(editorRoute('vocabulary.destroy', { vocabulary: props.vocabulary, term: pendingDelete.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}
</script>

<template>
    <DataTable :columns="columns" :rows="terms"
               :empty-title="$t('arcadia.editor.vocabulary.empty')"
>
        <template #toolbar>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="m-0 max-w-prose text-sm text-ink-3">{{ $t(`arcadia.editor.vocabulary.hints.${vocabulary}`) }}</p>
                <Button type="button" variant="brand" size="sm" @click="dialog = {}">
                    <Plus :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.vocabulary.add') }}
                </Button>
            </div>
        </template>

        <template #row="{ row }">
            <td class="px-4 py-3 align-top">
                <div class="font-medium text-ink">{{ row.name }}</div>
            </td>
            <td class="px-4 py-3 align-top text-right font-mono text-xs whitespace-nowrap text-ink-4">
                {{ transChoice('arcadia.editor.vocabulary.used_by', row.guides_count, { count: row.guides_count }) }}
            </td>
            <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                <Button type="button" variant="ghost" size="icon-sm" @click="dialog = row" :aria-label="$t('arcadia.editor.vocabulary.save')">
                    <Pencil :size="14" aria-hidden="true" />
                </Button>
                <Button type="button" variant="ghost-danger" size="icon-sm" class="ml-1" @click="pendingDelete = row"
                        :aria-label="$t('arcadia.editor.vocabulary.delete.confirm')">
                    <Trash2 :size="14" aria-hidden="true" />
                </Button>
            </td>
        </template>
    </DataTable>

    <TermDialog :open="dialog !== null" :vocabulary="vocabulary" :term="dialog?.id ? dialog : null" @close="dialog = null" />

    <ConfirmDialog :open="pendingDelete !== null"
                   :title="$t('arcadia.editor.vocabulary.delete.title')"
                   :body="$t('arcadia.editor.vocabulary.delete.body')"
                   :confirm-label="$t('arcadia.editor.vocabulary.delete.confirm')"
                   :processing="deleting"
                   @confirm="confirmDelete"
                   @cancel="pendingDelete = null" />
</template>
