<script setup>
/**
 * This language's motivation phrases — short lines a reader reads in a glance — grouped by the
 * moment they are shown at: arriving at "My guides", and on a guide's card for where they stand
 * (not started, under way, nearly done, done). `{name}` becomes the reader's name.
 *
 * Added at the top, changed in place, deleted after a confirm. Each says how many guides chose it;
 * a guide that chose none draws on all of them (`MotivationPicker`).
 */
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Check, Pencil, Plus, Trash2, X } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // [{ id, moment, text, guides_count }]
    phrases: { type: Array, required: true },
    // [{ value, label }], in the order they come in a reader's path.
    moments: { type: Array, required: true },
    maxLength: { type: Number, required: true },
})

const editorRoute = useEditorRoute()

const groups = computed(() => props.moments.map(moment => ({
    ...moment,
    phrases: props.phrases.filter(phrase => phrase.moment === moment.value),
})))

const form = useForm({ moment: props.moments[0]?.value, text: '' })

const add = () => form.post(editorRoute('phrases.store'), {
    preserveScroll: true,
    // Keeps the moment, for adding several to the same one.
    onSuccess: () => form.reset('text'),
})

// The phrase being changed, and its text so far.
const editingId = ref(null)
const editing = ref('')

const edit = phrase => {
    editingId.value = phrase.id
    editing.value = phrase.text
}

const save = phrase => router.put(editorRoute('phrases.update', phrase.id), { moment: phrase.moment, text: editing.value }, {
    preserveScroll: true,
    onSuccess: () => { editingId.value = null },
})

const pendingDelete = ref(null)
const deleting = ref(false)

const confirmDelete = () => {
    deleting.value = true
    router.delete(editorRoute('phrases.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            pendingDelete.value = null
        },
    })
}
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.phrases.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.phrases.title') }]" />

        <PageHeader :title="$t('arcadia.editor.phrases.title')" :description="$t('arcadia.editor.phrases.description')" :count="phrases.length" />

        <form class="panel mb-6 max-w-3xl p-5" @submit.prevent="add">
            <Field :data-invalid="form.errors.text ? true : undefined">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Select v-model="form.moment">
                        <SelectTrigger class="sm:w-48" :aria-label="$t('arcadia.editor.phrases.moment')"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="moment in moments" :key="moment.value" :value="moment.value">{{ moment.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input v-model="form.text" :maxlength="maxLength" class="flex-1" autocomplete="off"
                           :placeholder="$t('arcadia.editor.phrases.placeholder')" :aria-label="$t('arcadia.editor.phrases.text')" />
                    <Button type="submit" variant="brand" :disabled="form.processing || !form.text.trim()">
                        <Plus :size="16" aria-hidden="true" />
                        {{ $t('arcadia.editor.phrases.add') }}
                    </Button>
                </div>
                <FieldError v-if="form.errors.text">{{ form.errors.text }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.phrases.hint', { max: maxLength }) }}</FieldDescription>
            </Field>
        </form>

        <div class="grid max-w-3xl gap-5">
            <section v-for="group in groups" :key="group.value" class="panel p-5">
                <h2 class="m-0 flex items-center gap-2 text-sm font-semibold text-ink">
                    {{ group.label }}
                    <Badge variant="neutral" class="px-1.5 py-0 font-mono text-[10px]">{{ group.phrases.length }}</Badge>
                </h2>
                <p class="mt-0.5 mb-3 text-xs text-ink-4">{{ $t(`arcadia.editor.phrases.when.${group.value}`) }}</p>

                <p v-if="!group.phrases.length" class="m-0 text-sm text-ink-4">{{ $t('arcadia.editor.phrases.empty') }}</p>
                <ul v-else class="m-0 divide-y divide-rule p-0">
                    <li v-for="phrase in group.phrases" :key="phrase.id" class="flex list-none items-center gap-3 py-2">
                        <form v-if="editingId === phrase.id" class="flex flex-1 items-center gap-1.5" @submit.prevent="save(phrase)">
                            <Input v-model="editing" :maxlength="maxLength" class="h-8 flex-1" :aria-label="$t('arcadia.editor.phrases.text')" @keydown.esc="editingId = null" />
                            <Button type="submit" size="icon-sm" variant="ghost" :aria-label="$t('arcadia.editor.phrases.save')"><Check :size="14" aria-hidden="true" /></Button>
                            <Button type="button" size="icon-sm" variant="ghost" :aria-label="$t('arcadia.editor.cancel')" @click="editingId = null"><X :size="14" aria-hidden="true" /></Button>
                        </form>
                        <template v-else>
                            <span class="min-w-0 flex-1 text-sm text-ink">{{ phrase.text }}</span>
                            <span class="shrink-0 font-mono text-[11px] text-ink-4">{{ $tChoice('arcadia.editor.phrases.used_by', phrase.guides_count, { count: phrase.guides_count }) }}</span>
                            <Button type="button" size="icon-sm" variant="ghost" :aria-label="$t('arcadia.editor.phrases.edit')" @click="edit(phrase)"><Pencil :size="14" aria-hidden="true" /></Button>
                            <Button type="button" size="icon-sm" variant="ghost-danger" :aria-label="$t('arcadia.editor.phrases.delete.action')" @click="pendingDelete = phrase"><Trash2 :size="14" aria-hidden="true" /></Button>
                        </template>
                    </li>
                </ul>
            </section>
        </div>

        <ConfirmDialog :open="pendingDelete !== null"
                       :title="$t('arcadia.editor.phrases.delete.title')"
                       :body="$t('arcadia.editor.phrases.delete.body')"
                       :confirm-label="$t('arcadia.editor.phrases.delete.confirm')"
                       :processing="deleting"
                       @confirm="confirmDelete"
                       @cancel="pendingDelete = null" />
    </div>
</template>
