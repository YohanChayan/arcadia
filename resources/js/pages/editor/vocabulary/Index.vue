<script setup>
/**
 * The three vocabularies guides are described with, in this site's language, one tab each.
 */
import { ref } from 'vue'
import { FolderTree, Tags } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import VocabularyList from '@/components/editor/VocabularyList.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'

defineProps({
    lists: { type: Object, required: true },
})

// Categories and topics: what guides are grouped by. A guide's goals are its own words, written
// on the guide (ADR 0020).
const tabs = [
    { key: 'categories', icon: FolderTree },
    { key: 'topics', icon: Tags },
]

const active = ref('categories')
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.vocabulary.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.vocabulary.title') }]" />

        <PageHeader :title="$t('arcadia.editor.vocabulary.title')" :description="$t('arcadia.editor.vocabulary.description')" />

        <Tabs v-model="active" :unmount-on-hide="false" class="gap-0">
            <TabsList class="mb-5 h-auto w-full justify-start gap-1 overflow-x-auto rounded-none border-b border-rule bg-transparent p-0">
                <TabsTrigger v-for="tab in tabs" :key="tab.key" :value="tab.key"
                             class="-mb-px h-auto flex-none gap-2 rounded-none border-0 border-b-2 border-transparent px-4 py-2.5 text-ink-3 hover:text-ink data-[state=active]:border-accent data-[state=active]:bg-transparent data-[state=active]:text-ink data-[state=active]:shadow-none">
                    <component :is="tab.icon" :size="16" aria-hidden="true" />
                    {{ $t(`arcadia.editor.vocabulary.tabs.${tab.key}`) }}
                    <Badge variant="neutral" class="px-1.5 py-0 font-mono text-[10px]">{{ lists[tab.key].length }}</Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent v-for="tab in tabs" :key="tab.key" :value="tab.key">
                <VocabularyList :vocabulary="tab.key" :terms="lists[tab.key]" />
            </TabsContent>
        </Tabs>
    </div>
</template>
