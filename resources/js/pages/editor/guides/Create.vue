<script setup>
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import GuideForm from '@/components/editor/GuideForm.vue'

defineProps({
    levels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    categories: { type: Array, required: true },
    topics: { type: Array, required: true },
    phrases: { type: Array, default: () => [] },
    moments: { type: Array, default: () => [] },
})
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.guides.new')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: $t('arcadia.editor.guides.new') }]" />

        <PageHeader :title="$t('arcadia.editor.guides.new')" />
        <GuideForm v-bind="$props" :submit-label="$t('arcadia.editor.guides.save')" />
    </div>
</template>
