/**
 * The editor's record of a test conversation: every turn's metrics as the chat reports them, the
 * one the tabs show, and what the whole conversation converted and cost. Nothing is fetched —
 * the chat's `turn-completed` event feeds `record`, its `reset` event `clear`.
 */
import { computed, ref } from 'vue';
import type {
    AssistantConversionRow,
    AssistantMetrics,
    AssistantTurnMetrics,
    AssistantUsageTotals,
} from '@/types';

export function useAssistantMetrics(): AssistantMetrics {
    const turns = ref<AssistantTurnMetrics[]>([]);
    const selected = ref(0);

    const turn = computed<AssistantTurnMetrics | undefined>(
        () => turns.value[selected.value],
    );

    const conversions = computed<AssistantConversionRow[]>(() =>
        turns.value.flatMap((each, index) =>
            each.conversion ? [{ turn: index + 1, ...each.conversion }] : [],
        ),
    );

    const totals = computed<AssistantUsageTotals>(() =>
        turns.value.reduce<AssistantUsageTotals>(
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
            { input: 0, cached: 0, output: 0, cost: 0 },
        ),
    );

    // The newest turn is the one shown.
    function record(metrics: AssistantTurnMetrics): void {
        turns.value.push(metrics);
        selected.value = turns.value.length - 1;
    }

    function clear(): void {
        turns.value = [];
        selected.value = 0;
    }

    return { turns, selected, turn, conversions, totals, record, clear };
}
