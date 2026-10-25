<script setup>
/**
 * The table pattern for signed-in screens.
 *
 * Each screen once built its own `<table>` with its own header styling, its own empty state
 * and, on two of them, no empty state at all — an empty result just rendered nothing, which
 * reads as a broken page rather than an empty one.
 *
 * Columns are declared once and drive the header, the empty-state colspan and the responsive
 * labels. Rows come from the `row` slot so each screen keeps control of its own cells.
 * Built on `ui/table`, `ui/empty` and `ui/spinner`; shadcn's own "data table" is a TanStack
 * recipe, which would be a dependency for sorting nothing here needs.
 */
import { Inbox } from '@lucide/vue'
import { Spinner } from '@/components/ui/spinner'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Table, TableBody, TableHead, TableHeader, TableRow } from '@/components/ui/table'

defineProps({
    // [{ key, label, align?: 'left'|'right'|'center', width?: string, class?: string }]
    // `class` goes on the header cell: a column hidden below a breakpoint (`hidden xl:table-cell`)
    // gives its body cells the same class, and the screen shows the value elsewhere meanwhile.
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: '' },
    emptyHint: { type: String, default: '' },
    rowKey: { type: String, default: 'id' },
})

const alignment = {
    right: 'text-right',
    center: 'text-center',
    left: 'text-left',
}
</script>

<template>
    <div class="overflow-hidden rounded-panel border border-rule bg-raised/40">
        <div v-if="$slots.toolbar" class="border-b border-rule p-4">
            <slot name="toolbar" />
        </div>

        <!-- `Table` carries its own horizontal scroll, so a wide table never scrolls the page. -->
        <Table class="border-collapse">
            <TableHeader>
                <TableRow class="border-rule hover:bg-transparent">
                    <TableHead
                        v-for="col in columns"
                        :key="col.key"
                        scope="col"
                        :style="col.width ? { width: col.width } : null"
                        class="h-auto px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-4"
                        :class="[alignment[col.align] || 'text-left', col.class]"
                    >
                        {{ col.label }}
                    </TableHead>
                </TableRow>
            </TableHeader>

            <TableBody class="divide-y divide-rule">
                <tr v-if="loading">
                    <td :colspan="columns.length" class="px-4 py-12">
                        <div class="flex items-center justify-center gap-3 text-sm text-ink-4">
                            <Spinner class="size-[18px]" />
                            <span>{{ $t('admin.table.loading') }}</span>
                        </div>
                    </td>
                </tr>

                <tr v-else-if="!rows.length">
                    <td :colspan="columns.length">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon"><Inbox /></EmptyMedia>
                                <EmptyTitle>{{ emptyTitle || $t('admin.table.empty') }}</EmptyTitle>
                                <EmptyDescription v-if="emptyHint">{{ emptyHint }}</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </td>
                </tr>

                <TableRow v-for="row in rows" v-else :key="row[rowKey]" class="border-0 hover:bg-hover">
                    <slot name="row" :row="row" />
                </TableRow>
            </TableBody>
        </Table>

        <div v-if="$slots.footer" class="border-t border-rule p-4">
            <slot name="footer" />
        </div>
    </div>
</template>
