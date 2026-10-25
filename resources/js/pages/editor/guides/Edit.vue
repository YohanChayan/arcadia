<script setup>
import { ScrollText } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import GuideForm from '@/components/editor/GuideForm.vue'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    levels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    categories: { type: Array, required: true },
    topics: { type: Array, required: true },
    phrases: { type: Array, default: () => [] },
    moments: { type: Array, default: () => [] },
    // What researched it — template, version, values, request — or null for a guide by hand.
    provenance: { type: Object, default: null },
})

// The values it was filled with, empty ones left out.
const filledValues = () => Object.entries(props.provenance?.values ?? {}).filter(([, value]) => value !== '' && value !== null)

const editorRoute = useEditorRoute()
</script>

<template>
    <EditorTitle :parts="[guide.title, $t('arcadia.editor.guides.edit')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: guide.title, route: 'content.show', params: { guide: guide.id } }, { label: $t('arcadia.editor.guides.edit') }]" />

        <PageHeader :title="$t('arcadia.editor.guides.edit')" :description="guide.title" />
        <GuideForm v-bind="$props" :submit-label="$t('arcadia.editor.guides.update')" />

        <!-- Where it came from: the request it answers, the prompt that researched it, and what the
             research left out — for the owner only, never the reader. -->
        <section v-if="provenance || guide.curator_notes" class="panel mt-6 max-w-3xl p-6 sm:p-8">
            <h2 class="m-0 flex items-center gap-2 font-display text-base font-semibold text-ink">
                <ScrollText :size="16" class="text-accent" aria-hidden="true" />
                {{ $t('arcadia.editor.prompts.provenance.title') }}
            </h2>
            <p v-if="provenance" class="mt-1 mb-0 text-sm text-ink-3">
                <Link :href="editorRoute('prompts.show', { template: provenance.template_id, version: provenance.number })" class="text-accent">
                    {{ provenance.template }} · v{{ provenance.number }}
                </Link>
                <template v-if="provenance.inquiry">
                    · {{ $t('arcadia.editor.prompts.provenance.from_request', { who: provenance.inquiry.name || provenance.inquiry.topic }) }}
                </template>
            </p>
            <dl v-if="filledValues().length" class="mt-4 mb-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                <template v-for="[name, value] in filledValues()" :key="name">
                    <dt class="font-mono text-xs text-ink-4">{{ name }}</dt>
                    <dd class="m-0 text-ink-2">{{ value }}</dd>
                </template>
            </dl>

            <template v-if="guide.curator_notes">
                <div v-if="guide.curator_notes.discarded?.length" class="mt-5">
                    <h3 class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.editor.import.notes.discarded') }}</h3>
                    <ul class="mt-1.5 mb-0 space-y-1 pl-5 text-sm text-ink-2">
                        <li v-for="(entry, index) in guide.curator_notes.discarded" :key="index">
                            <span class="font-medium text-ink">{{ entry.resource ?? entry }}</span>
                            <span v-if="entry.reason" class="text-ink-3"> — {{ entry.reason }}</span>
                        </li>
                    </ul>
                </div>
                <div v-if="guide.curator_notes.unverified?.length" class="mt-4">
                    <h3 class="m-0 text-sm font-semibold text-warning">{{ $t('arcadia.editor.import.notes.unverified') }}</h3>
                    <ul class="mt-1.5 mb-0 space-y-1 pl-5 text-sm text-ink-2">
                        <li v-for="(entry, index) in guide.curator_notes.unverified" :key="index">{{ entry }}</li>
                    </ul>
                </div>
            </template>
        </section>
    </div>
</template>
