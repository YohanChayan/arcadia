<script setup>
/**
 * Two versions of a guide compared, node by node — matched by key, so a moved video is moved and
 * a renamed stage is renamed. Beside it, what the change logs say changed, so a change nobody
 * logged stands out before a release.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import DiffNode from '@/components/editor/content/DiffNode.vue'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, required: true },
    versions: { type: Array, required: true },
    from: { type: Number, required: true },
    to: { type: Number, required: true },
    diff: { type: Object, required: true },
    logged: { type: Array, required: true },
    labels: { type: Object, required: true },
})

const editorRoute = useEditorRoute()
const showUnchanged = ref(false)

const compare = (from, to) => router.get(editorRoute('compare', { guide: props.guide.id }), { from, to })

const LEVELS = ['sections', 'stages', 'cards', 'items']
const nothingChanged = computed(() => LEVELS.every(level => Object.values(props.diff.summary[level]).every(n => n === 0)))
</script>

<template>
    <EditorTitle :parts="[guide.title, $t('arcadia.editor.compare.title')]" />

    <div class="max-w-4xl py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: guide.title, route: 'content.show', params: { guide: guide.id } }, { label: $t('arcadia.editor.compare.title') }]" />

        <PageHeader :title="guide.title" :eyebrow="$t('arcadia.editor.compare.title')" />

        <div class="panel mb-6 flex flex-wrap items-end gap-3 p-4">
            <label class="flex flex-col gap-1 text-xs text-ink-4">
                {{ $t('arcadia.editor.compare.from') }}
                <Select :model-value="String(from)" @update:model-value="n => compare(Number(n), to)">
                    <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="v in versions" :key="v.id" :value="String(v.number)">{{ $t('arcadia.editor.content.version', { number: v.number }) }}</SelectItem>
                    </SelectContent>
                </Select>
            </label>
            <label class="flex flex-col gap-1 text-xs text-ink-4">
                {{ $t('arcadia.editor.compare.to') }}
                <Select :model-value="String(to)" @update:model-value="n => compare(from, Number(n))">
                    <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="v in versions" :key="v.id" :value="String(v.number)">{{ $t('arcadia.editor.content.version', { number: v.number }) }}</SelectItem>
                    </SelectContent>
                </Select>
            </label>
            <Button type="button" variant="outline" size="sm" :disabled="to === 1" @click="compare(1, to)">{{ $t('arcadia.editor.compare.since_base') }}</Button>
            <Button type="button" variant="outline" size="sm" :disabled="to === 1" @click="compare(to - 1, to)">{{ $t('arcadia.editor.compare.previous') }}</Button>
            <div class="ml-auto flex items-center gap-2">
                <Switch id="show-unchanged" v-model="showUnchanged" />
                <Label for="show-unchanged" class="cursor-pointer font-normal text-ink-2">{{ $t('arcadia.editor.compare.show_unchanged') }}</Label>
            </div>
        </div>

        <p v-if="from === to" class="panel p-4 text-sm text-ink-3">{{ $t('arcadia.editor.compare.same_version') }}</p>

        <template v-else>
            <section class="panel mb-6 p-5 sm:p-6">
                <h2 class="m-0 font-display text-base font-semibold text-ink">{{ $t('arcadia.editor.compare.logged.title') }}</h2>
                <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t('arcadia.editor.compare.logged.hint') }}</p>
                <ul v-if="logged.length" class="mt-3 divide-y divide-rule p-0">
                    <li v-for="change in logged" :key="change.id" class="flex list-none items-start gap-3 py-2">
                        <span class="font-mono text-xs text-ink-4">v{{ change.version }}</span>
                        <Badge variant="neutral">{{ labels.changes[change.kind] }}</Badge>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm text-ink">{{ change.title }}</div>
                            <p v-if="change.notes" class="mt-0.5 mb-0 text-xs text-ink-4">{{ change.notes }}</p>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 mb-0 text-sm text-ink-4">{{ $t('arcadia.editor.compare.logged.empty') }}</p>
            </section>

            <section class="panel p-5 sm:p-6">
                <h2 class="m-0 font-display text-base font-semibold text-ink">{{ $t('arcadia.editor.compare.computed') }}</h2>

                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4">
                    <div v-for="level in LEVELS" :key="level">
                        <dt class="text-ink-4">{{ $t(`arcadia.editor.compare.levels.${level}`) }}</dt>
                        <dd class="m-0 font-mono text-ink-2">{{ $t('arcadia.editor.compare.summary', diff.summary[level]) }}</dd>
                    </div>
                </dl>

                <p v-if="nothingChanged && !showUnchanged" class="mt-4 mb-0 text-sm text-ink-4">{{ $t('arcadia.editor.compare.no_changes') }}</p>

                <div class="mt-4 flex flex-col gap-2">
                    <DiffNode v-for="node in diff.sections" :key="node.key" :node="node" level="sections" :labels="labels" :show-unchanged="showUnchanged" />
                    <DiffNode v-for="node in diff.stages" :key="node.key" :node="node" level="stages" :labels="labels" :show-unchanged="showUnchanged" />
                </div>
            </section>
        </template>
    </div>
</template>
