<script setup>
/**
 * The lists guides choose from, in this site's language, one tab each: the two vocabularies they
 * are grouped by, and the motivation phrases their readers see. `?tab=` opens one.
 */
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { FolderTree, MessageSquareHeart, Tags } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import VocabularyList from '@/components/editor/VocabularyList.vue'
import PhraseList from '@/components/editor/PhraseList.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'

const props = defineProps({
    lists: { type: Object, required: true },
    // Every guide of this language, for the categories tab's categorizer.
    guides: { type: Array, required: true },
    // The phrases tab's: [{ id, moment, text, guides_count }], the moments, the longest a phrase can be.
    phrases: { type: Array, required: true },
    moments: { type: Array, required: true },
    maxLength: { type: Number, required: true },
})

// Categories and topics: what guides are grouped by. A guide's goals are its own words, written
// on the guide (ADR 0020).
const tabs = computed(() => [
    { key: 'categories', icon: FolderTree, count: props.lists.categories.length },
    { key: 'topics', icon: Tags, count: props.lists.topics.length },
    { key: 'phrases', icon: MessageSquareHeart, count: props.phrases.length },
])

// The page's URL, not `window`, so the tab asked for also opens when rendered on the server.
const requested = new URL(usePage().url, 'http://localhost').searchParams.get('tab')
const active = ref(tabs.value.some(tab => tab.key === requested) ? requested : 'categories')
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.vocabulary.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.vocabulary.title') }]" />

        <PageHeader :title="$t('arcadia.editor.vocabulary.title')" :description="$t('arcadia.editor.vocabulary.description')" />

        <Tabs v-model="active" :unmount-on-hide="false" class="gap-0">
            <TabsList class="mb-5 h-auto max-w-full flex-wrap justify-start gap-1 rounded-lg bg-well p-1">
                <TabsTrigger v-for="tab in tabs" :key="tab.key" :value="tab.key"
                             class="h-auto flex-none gap-2 rounded-md border-0 px-3.5 py-1.5 text-ink-3 hover:text-ink data-[state=active]:bg-raised-solid data-[state=active]:text-ink data-[state=active]:shadow-[var(--shadow-sm)] dark:data-[state=active]:bg-raised-solid dark:data-[state=active]:text-ink">
                    <component :is="tab.icon" :size="16" aria-hidden="true" />
                    {{ $t(`arcadia.editor.vocabulary.tabs.${tab.key}`) }}
                    <Badge variant="neutral" class="px-1.5 py-0 font-mono text-[10px]">{{ tab.count }}</Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent v-for="tab in tabs" :key="tab.key" :value="tab.key">
                <PhraseList v-if="tab.key === 'phrases'" :phrases="phrases" :moments="moments" :max-length="maxLength" />
                <VocabularyList v-else :vocabulary="tab.key" :terms="lists[tab.key]" :guides="guides" />
            </TabsContent>
        </Tabs>
    </div>
</template>
