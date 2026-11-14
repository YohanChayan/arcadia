<script setup>
/**
 * The categorizer: one category's guides beside a search over every guide of this language, to
 * file several at once. A guide already in another category says which; filing it here moves it,
 * and the dialog asks before it does (the request refuses an unasked move too).
 *
 * The guides come whole from the Vocabulary page and are filtered here. Each change goes back
 * through Inertia with the dialog's state kept, so both columns redraw from the saved props.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { Search, X } from '@lucide/vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import Eyebrow from '@/components/common/Eyebrow.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useEditorRoute } from '@/composables/useEditorRoute.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    // The category being filled, or null while closed.
    category: { type: Object, default: null },
    // This language's categories, to name the one a guide is already in.
    categories: { type: Array, required: true },
    // Every guide of this language: { id, title, status, category_id }.
    guides: { type: Array, required: true },
});

const emit = defineEmits(['close']);
const editorRoute = useEditorRoute();

const query = ref('');
const selected = ref([]);
const error = ref(null);
const confirmingMove = ref(false);
const processing = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        query.value = '';
        selected.value = [];
        error.value = null;
    },
);

const categoryNames = computed(
    () =>
        new Map(
            props.categories.map((category) => [category.id, category.name]),
        ),
);

// "Cómo", "como" and "COMO" are one search.
const fold = (text) =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

const inside = computed(() =>
    props.guides.filter((guide) => guide.category_id === props.category?.id),
);

const results = computed(() => {
    const words = fold(query.value).split(/\s+/).filter(Boolean);

    return props.guides.filter(
        (guide) =>
            guide.category_id !== props.category?.id &&
            words.every((word) => fold(guide.title).includes(word)),
    );
});

const isSelected = (id) => selected.value.includes(id);
const toggle = (id) => {
    selected.value = isSelected(id)
        ? selected.value.filter((other) => other !== id)
        : [...selected.value, id];
};

// The selected guides that would leave another category.
const moving = computed(() =>
    props.guides.filter(
        (guide) => isSelected(guide.id) && guide.category_id !== null,
    ),
);

const moveBody = computed(() =>
    trans('arcadia.editor.vocabulary.categorizer.move.body', {
        guides: moving.value
            .map(
                (guide) =>
                    `«${guide.title}» (${categoryNames.value.get(guide.category_id)})`,
            )
            .join(', '),
        category: props.category?.name ?? '',
    }),
);

const file = (move = false) => {
    if (moving.value.length > 0 && !move) {
        confirmingMove.value = true;
        return;
    }

    processing.value = true;
    router.post(
        editorRoute('vocabulary.categories.guides.store', {
            term: props.category.id,
        }),
        { guide_ids: selected.value, move },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                selected.value = [];
                error.value = null;
            },
            onError: (errors) => {
                error.value =
                    errors.guide_ids ?? Object.values(errors)[0] ?? null;
            },
            onFinish: () => {
                processing.value = false;
                confirmingMove.value = false;
            },
        },
    );
};

const remove = (guide) => {
    router.delete(
        editorRoute('vocabulary.categories.guides.destroy', {
            term: props.category.id,
            guide: guide.id,
        }),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('close')">
        <DialogContent class="max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ category?.name }}</DialogTitle>
                <DialogDescription>{{
                    $t('arcadia.editor.vocabulary.categorizer.description')
                }}</DialogDescription>
            </DialogHeader>

            <div class="grid min-w-0 gap-6 md:grid-cols-2">
                <section class="min-w-0">
                    <Eyebrow as="h3" class="mb-2">
                        {{ $t('arcadia.editor.vocabulary.categorizer.inside') }}
                        · {{ inside.length }}
                    </Eyebrow>
                    <ul
                        v-if="inside.length"
                        class="m-0 max-h-[50vh] list-none divide-y divide-rule overflow-y-auto rounded-md border border-rule p-0"
                    >
                        <li
                            v-for="guide in inside"
                            :key="guide.id"
                            class="flex items-center gap-2 px-3 py-2"
                        >
                            <span class="min-w-0 flex-1 text-sm text-ink">{{
                                guide.title
                            }}</span>
                            <Badge
                                variant="neutral"
                                class="shrink-0 py-0 text-[10px]"
                                >{{
                                    $t(
                                        `arcadia.editor.vocabulary.categorizer.statuses.${guide.status}`,
                                    )
                                }}</Badge
                            >
                            <Button
                                type="button"
                                variant="ghost-danger"
                                size="icon-sm"
                                class="shrink-0"
                                @click="remove(guide)"
                                :aria-label="
                                    $t(
                                        'arcadia.editor.vocabulary.categorizer.remove',
                                    )
                                "
                            >
                                <X :size="14" aria-hidden="true" />
                            </Button>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="m-0 rounded-md border border-dashed border-rule px-3 py-6 text-center text-sm text-ink-3"
                    >
                        {{
                            $t(
                                'arcadia.editor.vocabulary.categorizer.inside_empty',
                            )
                        }}
                    </p>
                </section>

                <section class="min-w-0">
                    <Eyebrow
                        as="label"
                        for="categorizer-search"
                        class="mb-2 block"
                        >{{
                            $t('arcadia.editor.vocabulary.categorizer.search')
                        }}</Eyebrow
                    >
                    <div class="relative mb-2">
                        <Search
                            :size="16"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-4"
                            aria-hidden="true"
                        />
                        <Input
                            id="categorizer-search"
                            v-model="query"
                            type="search"
                            autocomplete="off"
                            class="pl-9"
                            :placeholder="
                                $t(
                                    'arcadia.editor.vocabulary.categorizer.search_placeholder',
                                )
                            "
                        />
                    </div>
                    <ul
                        v-if="results.length"
                        class="m-0 max-h-[50vh] list-none divide-y divide-rule overflow-y-auto rounded-md border border-rule p-0"
                    >
                        <li v-for="guide in results" :key="guide.id">
                            <label
                                :for="`categorizer-guide-${guide.id}`"
                                class="flex cursor-pointer items-start gap-3 px-3 py-2 hover:bg-hover"
                            >
                                <Checkbox
                                    :id="`categorizer-guide-${guide.id}`"
                                    class="mt-0.5"
                                    :model-value="isSelected(guide.id)"
                                    @update:model-value="toggle(guide.id)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-ink">{{
                                        guide.title
                                    }}</span>
                                    <span class="mt-1 flex flex-wrap gap-1">
                                        <Badge
                                            v-if="guide.category_id"
                                            variant="pending"
                                            class="py-0 text-[10px]"
                                            >{{
                                                categoryNames.get(
                                                    guide.category_id,
                                                )
                                            }}</Badge
                                        >
                                        <Badge
                                            v-else
                                            variant="neutral"
                                            class="py-0 text-[10px]"
                                            >{{
                                                $t(
                                                    'arcadia.editor.vocabulary.categorizer.uncategorised',
                                                )
                                            }}</Badge
                                        >
                                        <Badge
                                            variant="neutral"
                                            class="py-0 text-[10px]"
                                            >{{
                                                $t(
                                                    `arcadia.editor.vocabulary.categorizer.statuses.${guide.status}`,
                                                )
                                            }}</Badge
                                        >
                                    </span>
                                </span>
                            </label>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="m-0 rounded-md border border-dashed border-rule px-3 py-6 text-center text-sm text-ink-3"
                    >
                        {{
                            $t(
                                'arcadia.editor.vocabulary.categorizer.results_empty',
                            )
                        }}
                    </p>
                </section>
            </div>

            <p v-if="error" class="m-0 text-sm text-danger" role="alert">
                {{ error }}
            </p>

            <DialogFooter class="items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm text-ink-3">
                    {{
                        transChoice(
                            'arcadia.editor.vocabulary.categorizer.selected',
                            selected.length,
                            { count: selected.length },
                        )
                    }}
                    <Button
                        v-if="selected.length"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="selected = []"
                    >
                        {{ $t('arcadia.editor.vocabulary.categorizer.clear') }}
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                        >{{ $t('arcadia.editor.cancel') }}</Button
                    >
                    <Button
                        type="button"
                        variant="brand"
                        :disabled="processing || !selected.length"
                        @click="file()"
                    >
                        {{ $t('arcadia.editor.vocabulary.categorizer.add') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="confirmingMove"
        :title="$t('arcadia.editor.vocabulary.categorizer.move.title')"
        :body="moveBody"
        :confirm-label="
            $t('arcadia.editor.vocabulary.categorizer.move.confirm')
        "
        :processing="processing"
        @confirm="file(true)"
        @cancel="confirmingMove = false"
    />
</template>
