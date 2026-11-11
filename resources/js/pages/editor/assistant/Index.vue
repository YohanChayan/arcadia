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
import { computed, ref } from 'vue';
import AssistantChat from '@/components/assistant/AssistantChat.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue';
import EditorTitle from '@/components/editor/EditorTitle.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useEditorRoute } from '@/composables/useEditorRoute.js';
import type { AssistantTurnMetrics } from '@/types';

const props = defineProps<{
    provider: string;
    models: { planner: string; speaker: string };
    hasKey: boolean;
    cacheInstructions: boolean;
    maxLength: number;
}>();

const editorRoute = useEditorRoute();
const messagesUrl = editorRoute('assistant.messages');

const turns = ref<AssistantTurnMetrics[]>([]);
const selected = ref(0);
const tab = ref('passages');

const tabs = [
    { key: 'passages', icon: FileSearch },
    { key: 'engagement', icon: Gauge },
    { key: 'usage', icon: Coins },
    { key: 'conversions', icon: BadgeCheck },
] as const;

const turn = computed<AssistantTurnMetrics | undefined>(
    () => turns.value[selected.value],
);

const conversions = computed(() =>
    turns.value.flatMap((each, index) =>
        each.conversion ? [{ turn: index + 1, ...each.conversion }] : [],
    ),
);

const totals = computed(() =>
    turns.value.reduce(
        (sum, each) => ({
            input: sum.input + each.usage.input_tokens,
            cached: sum.cached + (each.usage.cached_input_tokens ?? 0),
            output: sum.output + each.usage.output_tokens,
            // One unpriced turn leaves the conversation without a cost.
            cost:
                sum.cost === null || each.cost === null
                    ? null
                    : sum.cost + each.cost,
        }),
        { input: 0, cached: 0, output: 0, cost: 0 as number | null },
    ),
);

const integer = new Intl.NumberFormat();

function tokens(count: number | null): string {
    return count === null ? '—' : integer.format(count);
}

function money(cost: number | null): string {
    return cost === null ? '' : `$${cost.toFixed(6)}`;
}

function record(metrics: AssistantTurnMetrics): void {
    turns.value.push(metrics);
    selected.value = turns.value.length - 1;
}

function clear(): void {
    turns.value = [];
    selected.value = 0;
}
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.assistant.title')]" />

    <div class="py-6">
        <EditorBreadcrumb
            :trail="[{ label: $t('arcadia.editor.assistant.title') }]"
        />

        <PageHeader
            :title="$t('arcadia.editor.assistant.title')"
            :description="$t('arcadia.editor.assistant.description')"
        />

        <p
            v-if="!props.hasKey"
            role="alert"
            class="mb-4 flex items-center gap-2 rounded-control border border-warning/30 bg-warning/10 px-4 py-2.5 text-sm text-warning"
        >
            <TriangleAlert class="size-4 shrink-0" />
            {{
                $t('arcadia.editor.assistant.no_key', {
                    provider: props.provider,
                })
            }}
        </p>

        <p class="mb-4 font-mono text-xs text-ink-3">
            {{
                $t('arcadia.editor.assistant.models', {
                    planner: props.models.planner,
                    speaker: props.models.speaker,
                })
            }}
            ·
            {{
                props.cacheInstructions
                    ? $t('arcadia.editor.assistant.cache_on')
                    : $t('arcadia.editor.assistant.cache_off')
            }}
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <AssistantChat
                class="h-[70vh] min-h-[28rem]"
                :url="messagesUrl"
                :greeting="$t('arcadia.assistant.greeting')"
                @turn-completed="record"
                @reset="clear"
            />

            <section
                class="panel flex h-[70vh] min-h-[28rem] flex-col overflow-hidden"
                :aria-label="$t('arcadia.editor.assistant.metrics')"
            >
                <p
                    v-if="turns.length === 0"
                    class="m-auto max-w-xs p-6 text-center text-sm text-ink-3"
                >
                    {{ $t('arcadia.editor.assistant.empty') }}
                </p>

                <template v-else>
                    <div
                        class="flex flex-wrap gap-1.5 border-b border-rule p-3"
                        role="group"
                        :aria-label="$t('arcadia.editor.assistant.turns')"
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
                            {{
                                $t('arcadia.editor.assistant.turn', {
                                    number: String(index + 1),
                                })
                            }}
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
                                {{
                                    $t(
                                        `arcadia.editor.assistant.tabs.${each.key}`,
                                    )
                                }}
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
                                <p
                                    v-if="turn && turn.passages.length === 0"
                                    class="text-sm text-ink-3"
                                >
                                    {{
                                        $t(
                                            'arcadia.editor.assistant.no_passages',
                                        )
                                    }}
                                </p>
                                <ol
                                    v-else-if="turn"
                                    class="flex flex-col gap-3"
                                >
                                    <li
                                        v-for="(
                                            passage, index
                                        ) in turn.passages"
                                        :key="index"
                                        class="rounded-control border border-rule p-3"
                                    >
                                        <div
                                            class="mb-1 flex items-baseline justify-between gap-3"
                                        >
                                            <span class="font-medium text-ink">
                                                {{ passage.guide_title }}
                                                <span
                                                    class="font-mono text-xs text-ink-4"
                                                    >#{{
                                                        passage.guide_id
                                                    }}</span
                                                >
                                            </span>
                                            <span
                                                class="shrink-0 font-mono text-xs text-ink-3"
                                                >{{
                                                    passage.score.toFixed(2)
                                                }}
                                                · {{ passage.source }}</span
                                            >
                                        </div>
                                        <p class="text-sm text-ink-2">
                                            {{ passage.content }}
                                        </p>
                                    </li>
                                </ol>
                            </TabsContent>

                            <TabsContent value="engagement" class="mt-0">
                                <p class="mb-3 text-sm text-ink-3">
                                    {{
                                        $t(
                                            'arcadia.editor.assistant.engagement_lead',
                                        )
                                    }}
                                </p>
                                <ol class="flex flex-col gap-2">
                                    <li
                                        v-for="(each, index) in turns"
                                        :key="index"
                                        class="grid grid-cols-[4.5rem_1fr_2.5rem] items-center gap-3 text-sm"
                                    >
                                        <span class="text-ink-3">{{
                                            $t(
                                                'arcadia.editor.assistant.turn',
                                                {
                                                    number: String(index + 1),
                                                },
                                            )
                                        }}</span>
                                        <span
                                            class="h-2 overflow-hidden rounded-full bg-well"
                                        >
                                            <span
                                                class="block h-full rounded-full bg-accent"
                                                :style="{
                                                    width: `${each.engagement}%`,
                                                }"
                                            />
                                        </span>
                                        <span
                                            class="text-right font-mono text-ink"
                                            >{{ each.engagement }}</span
                                        >
                                    </li>
                                </ol>
                            </TabsContent>

                            <TabsContent value="usage" class="mt-0">
                                <table v-if="turn" class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-ink-3">
                                            <th class="pb-2 font-normal" />
                                            <th
                                                class="pb-2 text-right font-normal"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.this_turn',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="pb-2 text-right font-normal"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.conversation',
                                                    )
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="font-mono">
                                        <tr class="border-t border-rule">
                                            <th
                                                class="py-2 text-left font-sans font-normal text-ink-3"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.input',
                                                    )
                                                }}
                                            </th>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{
                                                    tokens(
                                                        turn.usage.input_tokens,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{ tokens(totals.input) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-rule">
                                            <th
                                                class="py-2 text-left font-sans font-normal text-ink-3"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.cached',
                                                    )
                                                }}
                                            </th>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{
                                                    turn.usage
                                                        .cached_input_tokens ===
                                                    null
                                                        ? $t(
                                                              'arcadia.editor.assistant.not_reported',
                                                          )
                                                        : tokens(
                                                              turn.usage
                                                                  .cached_input_tokens,
                                                          )
                                                }}
                                            </td>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{ tokens(totals.cached) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-rule">
                                            <th
                                                class="py-2 text-left font-sans font-normal text-ink-3"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.output',
                                                    )
                                                }}
                                            </th>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{
                                                    tokens(
                                                        turn.usage
                                                            .output_tokens,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{ tokens(totals.output) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-rule">
                                            <th
                                                class="py-2 text-left font-sans font-normal text-ink-3"
                                            >
                                                {{
                                                    $t(
                                                        'arcadia.editor.assistant.cost',
                                                    )
                                                }}
                                            </th>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{
                                                    turn.cost === null
                                                        ? $t(
                                                              'arcadia.editor.assistant.no_price',
                                                          )
                                                        : money(turn.cost)
                                                }}
                                            </td>
                                            <td
                                                class="py-2 text-right text-ink"
                                            >
                                                {{
                                                    totals.cost === null
                                                        ? $t(
                                                              'arcadia.editor.assistant.no_price',
                                                          )
                                                        : money(totals.cost)
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </TabsContent>

                            <TabsContent value="conversions" class="mt-0">
                                <p
                                    v-if="conversions.length === 0"
                                    class="text-sm text-ink-3"
                                >
                                    {{
                                        $t(
                                            'arcadia.editor.assistant.no_conversions',
                                        )
                                    }}
                                </p>
                                <ul v-else class="flex flex-col gap-2">
                                    <li
                                        v-for="conversion in conversions"
                                        :key="conversion.guide_id"
                                        class="flex items-center justify-between gap-3 rounded-control border border-success/25 bg-success/5 px-3 py-2 text-sm"
                                    >
                                        <span
                                            class="flex items-center gap-2 text-ink"
                                        >
                                            <BadgeCheck
                                                class="size-4 text-success"
                                            />
                                            {{ conversion.guide_title }}
                                            <span
                                                class="font-mono text-xs text-ink-4"
                                                >#{{
                                                    conversion.guide_id
                                                }}</span
                                            >
                                        </span>
                                        <span class="text-xs text-ink-3">{{
                                            $t(
                                                'arcadia.editor.assistant.turn',
                                                {
                                                    number: String(
                                                        conversion.turn,
                                                    ),
                                                },
                                            )
                                        }}</span>
                                    </li>
                                </ul>
                            </TabsContent>
                        </div>
                    </Tabs>
                </template>
            </section>
        </div>
    </div>
</template>
