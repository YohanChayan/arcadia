<script setup>
/**
 * An editor screen's `Breadcrumb`, from the editor's dashboard down: a screen passes only its own
 * trail — [{ label, route, params }], `route` an editor route's short name as `useEditorRoute`
 * takes it, left out on the screen itself — and the dashboard leads it.
 */
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import Breadcrumb from '@/components/common/Breadcrumb.vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    trail: { type: Array, required: true },
})

const editorRoute = useEditorRoute()

const items = computed(() => [
    { label: trans('editor.nav.items.home'), href: editorRoute('home') },
    ...props.trail.map(({ label, route, params }) => ({ label, href: route ? editorRoute(route, params) : null })),
])
</script>

<template>
    <Breadcrumb :items="items" :label="$t('editor.common.breadcrumb.label')" :back-label="$t('editor.common.breadcrumb.back')" />
</template>
