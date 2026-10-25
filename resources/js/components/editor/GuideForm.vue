<script setup>
/**
 * A guide's description, for create and edit alike: title, level, status, category, topics and
 * goals. The language is the host's and is not a field. The guide's content is edited on its
 * own screen.
 *
 * Goals are the guide's own words — one per line, nothing to register first (ADR 0020); topics
 * and the category are the shared vocabulary they are filed under. The motivation phrases it
 * draws on are chosen here too, by moment — only the moments a guide chooses for, as the server
 * sends them (not the welcome, which is every guide's); choosing none leaves it all of them.
 * "Suggest" picks a few per moment at random, on the form only — nothing is saved until the form is.
 */
import { useForm } from '@inertiajs/vue3'
import { ArrowLeft, Check, Shuffle } from '@lucide/vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import CheckboxGroup from '@/components/common/CheckboxGroup.vue'
import { Button, buttonVariants } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    guide: { type: Object, default: null },
    levels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    categories: { type: Array, required: true },
    topics: { type: Array, required: true },
    // [{ id, moment, text }] and the moments they are grouped by.
    phrases: { type: Array, default: () => [] },
    moments: { type: Array, default: () => [] },
    submitLabel: { type: String, required: true },
})

const editorRoute = useEditorRoute()
const isEdit = props.guide !== null

// reka's Select cannot hold null or an empty string, so "no category" travels as `none`
// and is sent as null.
const NO_CATEGORY = 'none'

const form = useForm({
    title: props.guide?.title ?? '',
    level: props.guide?.level ?? props.levels[0]?.value,
    status: props.guide?.status ?? 'draft',
    category_id: props.guide?.category_id ? String(props.guide.category_id) : NO_CATEGORY,
    topic_ids: props.guide?.topic_ids ?? [],
    phrase_ids: props.guide?.phrase_ids ?? [],
    // One goal per line in the box; sent as a list.
    goals: (props.guide?.goals ?? []).join('\n'),
    greeting: props.guide?.greeting ?? '',
})

const phrasesFor = moment => props.phrases.filter(phrase => phrase.moment === moment)

const togglePhrase = id => {
    form.phrase_ids = form.phrase_ids.includes(id)
        ? form.phrase_ids.filter(chosen => chosen !== id)
        : [...form.phrase_ids, id]
}

// Three to five of each moment's phrases, at random — all of them when it has fewer. Replaces
// the current choice; the owner adjusts it from there and saves with the form.
const SUGGEST_MIN = 3
const SUGGEST_MAX = 5

const suggestPhrases = () => {
    form.phrase_ids = props.moments.flatMap(moment => {
        const ids = phrasesFor(moment.value).map(phrase => phrase.id)

        for (let i = ids.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [ids[i], ids[j]] = [ids[j], ids[i]]
        }

        return ids.slice(0, SUGGEST_MIN + Math.floor(Math.random() * (SUGGEST_MAX - SUGGEST_MIN + 1)))
    })
}

const submit = () => {
    const payload = data => ({
        ...data,
        category_id: data.category_id === NO_CATEGORY ? null : Number(data.category_id),
        goals: data.goals.split('\n').map(line => line.trim()).filter(Boolean),
    })

    return isEdit
        ? form.transform(payload).put(editorRoute('guides.update', props.guide.id))
        : form.transform(payload).post(editorRoute('guides.store'))
}
</script>

<template>
    <form class="panel max-w-3xl p-6 sm:p-8" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-5">
            <Field :data-invalid="form.errors.title ? true : undefined">
                <FieldLabel for="title"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.title') }}</Eyebrow></FieldLabel>
                <Input id="title" v-model="form.title" type="text" autocomplete="off" :aria-invalid="form.errors.title ? 'true' : undefined" />
                <FieldError v-if="form.errors.title">{{ form.errors.title }}</FieldError>
            </Field>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <Field>
                    <FieldLabel for="level"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.level') }}</Eyebrow></FieldLabel>
                    <Select v-model="form.level">
                        <SelectTrigger id="level"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in levels" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel for="status"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.status') }}</Eyebrow></FieldLabel>
                    <Select v-model="form.status">
                        <SelectTrigger id="status"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field :data-invalid="form.errors.category_id ? true : undefined">
                    <FieldLabel for="category"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.category') }}</Eyebrow></FieldLabel>
                    <Select v-model="form.category_id">
                        <SelectTrigger id="category"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NO_CATEGORY">{{ $t('arcadia.editor.guides.fields.no_category') }}</SelectItem>
                            <SelectItem v-for="option in categories" :key="option.id" :value="String(option.id)">{{ option.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <FieldError v-if="form.errors.category_id">{{ form.errors.category_id }}</FieldError>
                </Field>
            </div>

            <Field :data-invalid="form.errors.goals || form.errors['goals.0'] ? true : undefined">
                <FieldLabel for="goals"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.goals') }}</Eyebrow></FieldLabel>
                <Textarea id="goals" v-model="form.goals" rows="4" :placeholder="$t('arcadia.editor.guides.fields.goals_placeholder')" />
                <FieldError v-if="form.errors.goals || form.errors['goals.0']">{{ form.errors.goals || form.errors['goals.0'] }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.guides.fields.goals_hint') }}</FieldDescription>
            </Field>

            <fieldset class="m-0 border-0 p-0">
                <legend class="mb-2"><Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.topics') }}</Eyebrow></legend>
                <CheckboxGroup v-model="form.topic_ids"
                               :options="topics.map(option => ({ value: option.id, label: option.name }))"
                               :empty="$t('arcadia.editor.guides.fields.none_yet')" />
                <FieldError v-if="form.errors.topic_ids || form.errors['topic_ids.0']" class="mt-1">
                    {{ form.errors.topic_ids || form.errors['topic_ids.0'] }}
                </FieldError>
            </fieldset>

            <!-- The motivation phrases its readers see, by moment. None chosen: all of them.
                 Toggle chips, hand-built as /draft's are until the library has a toggle group. -->
            <fieldset v-if="phrases.length" class="m-0 border-0 p-0">
                <legend class="mb-1 flex w-full flex-wrap items-center justify-between gap-2">
                    <Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.phrases') }}</Eyebrow>
                    <Button type="button" variant="outline" size="sm" @click="suggestPhrases">
                        <Shuffle aria-hidden="true" />
                        {{ $t('arcadia.editor.guides.fields.phrases_suggest') }}
                    </Button>
                </legend>
                <p class="mt-0 mb-3 text-xs text-ink-3">{{ $t('arcadia.editor.guides.fields.phrases_hint') }}</p>
                <div class="grid gap-4">
                    <div v-for="moment in moments" :key="moment.value">
                        <p class="m-0 mb-1.5 text-xs font-semibold text-ink-2">{{ moment.label }}</p>
                        <p v-if="phrasesFor(moment.value).length === 0" class="m-0 text-sm text-ink-4">{{ $t('arcadia.editor.phrases.empty') }}</p>
                        <div v-else class="flex flex-wrap gap-2" role="group" :aria-label="moment.label">
                            <button v-for="phrase in phrasesFor(moment.value)" :key="phrase.id" type="button"
                                    :aria-pressed="form.phrase_ids.includes(phrase.id)"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-left text-sm leading-snug text-ink transition-colors"
                                    :class="form.phrase_ids.includes(phrase.id) ? 'border-accent bg-accent/10' : 'border-rule bg-well text-ink-2 hover:border-edge'"
                                    @click="togglePhrase(phrase.id)">
                                <Check v-if="form.phrase_ids.includes(phrase.id)" :size="13" class="shrink-0 text-accent" aria-hidden="true" />
                                {{ phrase.text }}
                            </button>
                        </div>
                    </div>
                </div>
                <FieldError v-if="form.errors.phrase_ids || form.errors['phrase_ids.0']" class="mt-1">
                    {{ form.errors.phrase_ids || form.errors['phrase_ids.0'] }}
                </FieldError>
            </fieldset>

            <!-- The personal opening the reader sees first; an imported answer writes it. -->
            <Field :data-invalid="form.errors.greeting ? true : undefined">
                <FieldLabel for="greeting">
                    <Eyebrow as="span">{{ $t('arcadia.editor.guides.fields.greeting') }}</Eyebrow>
                    <span class="text-xs font-normal text-ink-4">· {{ $t('arcadia.editor.optional') }}</span>
                </FieldLabel>
                <Textarea id="greeting" v-model="form.greeting" rows="3" maxlength="2000" />
                <FieldError v-if="form.errors.greeting">{{ form.errors.greeting }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.guides.fields.greeting_hint') }}</FieldDescription>
            </Field>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-rule pt-6">
            <Button type="submit" variant="brand" size="lg" :disabled="form.processing">{{ submitLabel }}</Button>
            <Link :href="editorRoute('guides.index')" :class="buttonVariants({ variant: 'outline', size: 'lg' })" class="no-underline">
                <ArrowLeft :size="15" aria-hidden="true" />
                {{ $t('arcadia.editor.cancel') }}
            </Link>
        </div>
    </form>
</template>
