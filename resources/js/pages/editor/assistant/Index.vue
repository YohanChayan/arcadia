<script setup lang="ts">
/**
 * The sales assistant's test screen. The owner chats on the left as a visitor would; on the
 * right, what each turn retrieved, decided and cost — read from the `done` event the chat
 * reports, so nothing here is fetched. A turn is picked above the tabs; engagement and
 * conversions read the whole conversation. Starting over in the chat clears them.
 */
import {
    BadgeCheck,
    Coins,
    FileSearch,
    Gauge,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import type { Component } from 'vue';
import AssistantChat from '@/components/assistant/AssistantChat.vue';
import ConversionsTab from '@/components/assistant/metrics/ConversionsTab.vue';
import EngagementTab from '@/components/assistant/metrics/EngagementTab.vue';
import PassagesTab from '@/components/assistant/metrics/PassagesTab.vue';
import UsageTab from '@/components/assistant/metrics/UsageTab.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue';
import EditorTitle from '@/components/editor/EditorTitle.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAssistantMetrics } from '@/composables/useAssistantMetrics';
import { useEditorRoute } from '@/composables/useEditorRoute.js';
import { turnLabel } from '@/lib/assistant-format';
import type { AssistantMetricsTab, AssistantPageProps } from '@/types';

const props = defineProps<AssistantPageProps>();

const editorRoute = useEditorRoute();
const messagesUrl = editorRoute('assistant.messages');

const { turns, selected, turn, conversions, totals, record, clear } =
    useAssistantMetrics();

const tab = ref<AssistantMetricsTab>('passages');

const tabs: { key: AssistantMetricsTab; icon: Component }[] = [
    { key: 'passages', icon: FileSearch },
    { key: 'engagement', icon: Gauge },
    { key: 'usage', icon: Coins },
    { key: 'conversions', icon: BadgeCheck },
];
</script>

<template>
    <EditorTitle :parts="[$t('editor.assistant.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('editor.assistant.title') }]" />

        <PageHeader
            :title="$t('editor.assistant.title')"
            :description="$t('editor.assistant.description')"
        />

        <p
            v-if="!props.hasKey"
            role="alert"
            class="mb-4 flex items-center gap-2 rounded-control border border-warning/30 bg-warning/10 px-4 py-2.5 text-sm text-warning"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{
                $t('editor.assistant.no_key', {
                    provider: props.provider,
                })
            }}
        </p>

        <p class="mb-4 font-mono text-xs text-ink-3">
            {{
                $t('editor.assistant.models', {
                    planner: props.models.planner,
                    speaker: props.models.speaker,
                })
            }}
            ·
            {{
                props.cacheInstructions
                    ? $t('editor.assistant.cache_on')
                    : $t('editor.assistant.cache_off')
            }}
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <AssistantChat
                class="h-[70vh] min-h-[28rem]"
                :url="messagesUrl"
                :max-length="props.maxLength"
                :greeting="$t('assistant.greeting')"
                @turn-completed="record"
                @reset="clear"
            />

            <section
                class="panel flex h-[70vh] min-h-[28rem] flex-col overflow-hidden"
                :aria-label="$t('editor.assistant.metrics')"
            >
                <p
                    v-if="turns.length === 0"
                    class="m-auto max-w-xs p-6 text-center text-sm text-ink-3"
                >
                    {{ $t('editor.assistant.empty') }}
                </p>

                <template v-else>
                    <div
                        class="flex flex-wrap gap-1.5 border-b border-rule p-3"
                        role="group"
                        :aria-label="$t('editor.assistant.turns')"
                    >
                        <button
                            v-for="(_, index) in turns"
                            :key="index"
                            type="button"
                            :aria-pressed="index === selected"
                            class="rounded-full border px-3 py-1 text-xs transition-colors"
                            :class="
                                index === selected
                                    ? 'border-accent bg-accent/15 text-ink'
                                    : 'border-rule text-ink-3 hover:text-ink'
                            "
                            @click="selected = index"
                        >
                            {{ turnLabel(index + 1) }}
                        </button>
                    </div>

                    <Tabs
                        v-model="tab"
                        class="flex min-h-0 flex-1 flex-col gap-0"
                    >
                        <TabsList
                            class="h-auto w-full justify-start gap-1 overflow-x-auto rounded-none border-b border-rule bg-transparent p-0 px-2"
                        >
                            <TabsTrigger
                                v-for="each in tabs"
                                :key="each.key"
                                :value="each.key"
                                class="-mb-px h-auto flex-none gap-2 rounded-none border-0 border-b-2 border-transparent px-3 py-2.5 text-ink-3 hover:text-ink data-[state=active]:border-accent data-[state=active]:bg-transparent data-[state=active]:text-ink data-[state=active]:shadow-none"
                            >
                                <component
                                    :is="each.icon"
                                    :size="16"
                                    aria-hidden="true"
                                />
                                {{ $t(`editor.assistant.tabs.${each.key}`) }}
                                <Badge
                                    v-if="
                                        each.key === 'conversions' &&
                                        conversions.length > 0
                                    "
                                    variant="neutral"
                                    class="px-1.5 py-0 font-mono text-[10px]"
                                    >{{ conversions.length }}</Badge
                                >
                            </TabsTrigger>
                        </TabsList>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            <TabsContent value="passages" class="mt-0">
                                <PassagesTab
                                    v-if="turn"
                                    :passages="turn.passages"
                                />
                            </TabsContent>

                            <TabsContent value="engagement" class="mt-0">
                                <EngagementTab :turns="turns" />
                            </TabsContent>

                            <TabsContent value="usage" class="mt-0">
                                <UsageTab
                                    v-if="turn"
                                    :turn="turn"
                                    :totals="totals"
                                />
                            </TabsContent>

                            <TabsContent value="conversions" class="mt-0">
                                <ConversionsTab :conversions="conversions" />
                            </TabsContent>
                        </div>
                    </Tabs>
                </template>
            </section>
        </div>
    </div>
</template>
