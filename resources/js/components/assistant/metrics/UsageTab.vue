<script setup lang="ts">
/** Tokens and cost of the shown turn beside the whole conversation's. */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { formatCost, formatTokens } from '@/lib/assistant-format';
import type { UsageTabProps } from '@/types';

const props = defineProps<UsageTabProps>();

const rows = computed(() => [
    {
        label: 'editor.assistant.input',
        turn: formatTokens(props.turn.usage.input_tokens),
        conversation: formatTokens(props.totals.input),
    },
    {
        label: 'editor.assistant.cached',
        turn:
            props.turn.usage.cached_input_tokens === null
                ? trans('editor.assistant.not_reported')
                : formatTokens(props.turn.usage.cached_input_tokens),
        conversation: formatTokens(props.totals.cached),
    },
    {
        label: 'editor.assistant.output',
        turn: formatTokens(props.turn.usage.output_tokens),
        conversation: formatTokens(props.totals.output),
    },
    {
        label: 'editor.assistant.cost',
        turn: formatCost(props.turn.cost),
        conversation: formatCost(props.totals.cost),
    },
]);
</script>

<template>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-ink-3">
                <th class="pb-2 font-normal" />
                <th class="pb-2 text-right font-normal">
                    {{ $t('editor.assistant.this_turn') }}
                </th>
                <th class="pb-2 text-right font-normal">
                    {{ $t('editor.assistant.conversation') }}
                </th>
            </tr>
        </thead>
        <tbody class="font-mono">
            <tr
                v-for="row in rows"
                :key="row.label"
                class="border-t border-rule"
            >
                <th class="py-2 text-left font-sans font-normal text-ink-3">
                    {{ $t(row.label) }}
                </th>
                <td class="py-2 text-right text-ink">{{ row.turn }}</td>
                <td class="py-2 text-right text-ink">
                    {{ row.conversation }}
                </td>
            </tr>
        </tbody>
    </table>
</template>
