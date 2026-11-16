/** How the editor's assistant metrics write counts, costs and turn numbers. */
import { trans } from 'laravel-vue-i18n';

const integer = new Intl.NumberFormat();

export function formatTokens(count: number | null): string {
    return count === null ? '—' : integer.format(count);
}

// Null is a model with no price configured, said as much rather than shown as $0.
export function formatCost(cost: number | null): string {
    return cost === null
        ? trans('editor.assistant.no_price')
        : `$${cost.toFixed(6)}`;
}

/** "Turn 3": `number` counts from 1. */
export function turnLabel(number: number): string {
    return trans('editor.assistant.turn', { number: String(number) });
}
