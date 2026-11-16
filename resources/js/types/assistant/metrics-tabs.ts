/** Props of the editor's assistant metrics tabs (`components/assistant/metrics/`). */
import type {
    AssistantConversionRow,
    AssistantPassage,
    AssistantTurnMetrics,
    AssistantUsageTotals,
} from './metrics';

export type PassagesTabProps = { passages: AssistantPassage[] };

export type EngagementTabProps = { turns: AssistantTurnMetrics[] };

export type UsageTabProps = {
    turn: AssistantTurnMetrics;
    totals: AssistantUsageTotals;
};

export type ConversionsTabProps = { conversions: AssistantConversionRow[] };
