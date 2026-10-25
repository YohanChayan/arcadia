<script setup>
/**
 * Page controls for a paginated list, driven by the paginator's `current_page` and
 * `last_page`. It emits the page asked for; the screen decides how to fetch it.
 *
 * Built on `ui/pagination`, which collapses long runs into an ellipsis — the version it
 * replaced drew every page number, with hardcoded Spanish labels and a zoom on hover.
 */
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = defineProps({
    page: { type: Number, required: true },
    lastPage: { type: Number, required: true },
})

const emit = defineEmits(['update:page', 'change'])

const goToPage = requested => {
    if (requested < 1 || requested > props.lastPage || requested === props.page) {
        return
    }

    emit('update:page', requested)
    emit('change', requested)
}
</script>

<template>
    <!-- reka-ui counts in items, so one item per page turns `lastPage` into the total. -->
    <Pagination
        v-if="lastPage > 1"
        :page="page"
        :total="lastPage"
        :items-per-page="1"
        :sibling-count="1"
        show-edges
        :aria-label="$t('admin.pagination.label')"
        class="mt-2"
        @update:page="goToPage"
    >
        <PaginationContent v-slot="{ items }">
            <PaginationPrevious>
                <ChevronLeft />
                <span class="hidden sm:block">{{ $t('admin.pagination.previous') }}</span>
            </PaginationPrevious>

            <template v-for="(item, index) in items" :key="index">
                <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === page">
                    {{ item.value }}
                </PaginationItem>
                <PaginationEllipsis v-else :index="index" />
            </template>

            <PaginationNext>
                <span class="hidden sm:block">{{ $t('admin.pagination.next') }}</span>
                <ChevronRight />
            </PaginationNext>
        </PaginationContent>
    </Pagination>
</template>
