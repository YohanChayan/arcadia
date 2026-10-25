<script setup>
/**
 * What this version changed since the one before — typed, so the owner can later decide whether
 * someone holding an earlier version is offered it. Kept while the version is a draft; frozen with
 * it once released.
 */
import { inject } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Plus, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { FieldError } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

defineProps({
    changes: { type: Array, required: true },
})

const content = inject('content')

const form = useForm({ kind: content.kinds.changes[0]?.value, title: '', notes: '' })
const add = () => form.post(content.route('changes'), { preserveScroll: true, onSuccess: () => form.reset('title', 'notes') })
const remove = change => router.delete(content.route('change', change.id), { preserveScroll: true })
</script>

<template>
    <section class="panel mb-6 p-5 sm:p-6">
        <h2 class="m-0 font-display text-base font-semibold text-ink">{{ $t('arcadia.editor.versions.changes.title') }}</h2>
        <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.editor.versions.changes.hint') }}</p>

        <ul v-if="changes.length" class="mt-4 divide-y divide-rule p-0">
            <li v-for="change in changes" :key="change.id" class="flex list-none items-start gap-3 py-2">
                <Badge variant="neutral">{{ content.label('changes', change.kind) }}</Badge>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-ink">{{ change.title }}</div>
                    <p v-if="change.notes" class="mt-0.5 mb-0 text-xs text-ink-4">{{ change.notes }}</p>
                </div>
                <Button v-if="content.editable" type="button" variant="ghost-danger" size="icon-sm" @click="remove(change)"
                        :aria-label="$t('arcadia.editor.content.actions.delete')">
                    <X :size="14" aria-hidden="true" />
                </Button>
            </li>
        </ul>
        <p v-else class="mt-4 mb-0 text-sm text-ink-4">{{ $t('arcadia.editor.versions.changes.empty') }}</p>

        <form v-if="content.editable" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="add">
            <Select v-model="form.kind">
                <SelectTrigger class="sm:w-44" :aria-label="$t('arcadia.editor.versions.changes.fields.kind')"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in content.kinds.changes" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
            </Select>
            <div class="flex-1">
                <Input v-model="form.title" :placeholder="$t('arcadia.editor.versions.changes.fields.title')" :aria-label="$t('arcadia.editor.versions.changes.fields.title')" />
                <FieldError v-if="form.errors.title" class="mt-1">{{ form.errors.title }}</FieldError>
            </div>
            <Input v-model="form.notes" class="flex-1" :placeholder="`${$t('arcadia.editor.versions.changes.fields.notes')} · ${$t('arcadia.editor.optional')}`" :aria-label="$t('arcadia.editor.versions.changes.fields.notes')" />
            <Button type="submit" variant="outline" :disabled="form.processing || !form.title">
                <Plus :size="14" aria-hidden="true" />
                {{ $t('arcadia.editor.versions.changes.add') }}
            </Button>
        </form>
    </section>
</template>
