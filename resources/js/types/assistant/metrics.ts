/**
 * What each assistant turn retrieved, decided and cost — the `done` event's payload — and the
 * shapes the editor's metrics tabs derive from a conversation of them.
 */
import type { ComputedRef, Ref } from 'vue';

/** A passage the retriever handed the assistant for one turn. */
export type AssistantPassage = {
    guide_id: number;
    guide_title: string;
    source: string;
    score: number;
    content: string;
};

export type AssistantConversion = { guide_id: number; guide_title: string };

/** What one assistant turn cost and decided — read by the editor's metrics tabs. */
export type AssistantTurnMetrics = {
    turn_id: string;
    passages: AssistantPassage[];
    engagement: number;
    usage: {
        input_tokens: number;
        output_tokens: number;
        // Null when the provider does not report cache reads.
        cached_input_tokens: number | null;
    };
    // Null when the model has no price configured.
    cost: number | null;
    conversion: AssistantConversion | null;
};

/** A conversion, numbered by the turn (from 1) that made it. */
export type AssistantConversionRow = AssistantConversion & { turn: number };

/** The whole conversation's usage. `cost` is null once any turn was unpriced. */
export type AssistantUsageTotals = {
    input: number;
    cached: number;
    output: number;
    cost: number | null;
};

export type AssistantMetricsTab =
    | 'passages'
    | 'engagement'
    | 'usage'
    | 'conversions';

export type AssistantMetrics = {
    turns: Ref<AssistantTurnMetrics[]>;
    // The index of the turn the tabs show.
    selected: Ref<number>;
    turn: ComputedRef<AssistantTurnMetrics | undefined>;
    conversions: ComputedRef<AssistantConversionRow[]>;
    totals: ComputedRef<AssistantUsageTotals>;
    record: (metrics: AssistantTurnMetrics) => void;
    clear: () => void;
};
