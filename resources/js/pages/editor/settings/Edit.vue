<script setup>
/**
 * This host's settings: the price the landing page offers a guide at, in dollars — shown beside
 * its button and in its popup; nothing is charged through the site — and a short note shown
 * beside the price wherever it appears (`BCV`). New display options join this form.
 *
 * And how long a step of a new guide should take to watch: the default its research prompt
 * starts at, changed per guide when that prompt is filled.
 */
import { useForm } from '@inertiajs/vue3'
import PageHeader from '@/components/common/PageHeader.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import Price from '@/components/landing/Price.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    price: { type: String, required: true },
    priceNote: { type: String, default: null },
    stepMin: { type: Number, required: true },
    stepMax: { type: Number, required: true },
})

const editorRoute = useEditorRoute()

const form = useForm({ price: props.price, price_note: props.priceNote ?? '', step_min_minutes: props.stepMin, step_max_minutes: props.stepMax })

const submit = () => form.put(editorRoute('settings.update'), { preserveScroll: true })
</script>

<template>
    <EditorTitle :parts="[$t('arcadia.editor.settings.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.settings.title') }]" />

        <PageHeader :title="$t('arcadia.editor.settings.title')" :description="$t('arcadia.editor.settings.description')" />

        <form class="panel max-w-md p-6 sm:p-8" @submit.prevent="submit">
            <Field :data-invalid="form.errors.price ? true : undefined">
                <FieldLabel for="price"><Eyebrow as="span">{{ $t('arcadia.editor.settings.price') }}</Eyebrow></FieldLabel>
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-mono text-ink-3" aria-hidden="true">$</span>
                    <Input id="price" v-model="form.price" type="number" inputmode="decimal" min="0" max="999" step="0.01" class="pl-7 font-mono"
                           :aria-invalid="form.errors.price ? 'true' : undefined" />
                </div>
                <FieldError v-if="form.errors.price">{{ form.errors.price }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.settings.price_hint') }}</FieldDescription>
            </Field>

            <Field class="mt-6" :data-invalid="form.errors.price_note ? true : undefined">
                <FieldLabel for="price-note"><Eyebrow as="span">{{ $t('arcadia.editor.settings.price_note') }}</Eyebrow></FieldLabel>
                <div class="flex items-center gap-3">
                    <Input id="price-note" v-model="form.price_note" type="text" maxlength="24" autocomplete="off" class="max-w-40 font-mono uppercase"
                           :placeholder="$t('arcadia.editor.settings.price_note_placeholder')"
                           :aria-invalid="form.errors.price_note ? 'true' : undefined" />
                    <!-- How it reads on the landing page. -->
                    <Price v-if="Number(form.price) > 0" :amount="`$${Number(form.price)}`" :note="form.price_note.trim() || null" class="text-2xl" aria-hidden="true" />
                </div>
                <FieldError v-if="form.errors.price_note">{{ form.errors.price_note }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.settings.price_note_hint') }}</FieldDescription>
            </Field>

            <Field class="mt-6" :data-invalid="form.errors.step_min_minutes || form.errors.step_max_minutes ? true : undefined">
                <FieldLabel for="step-min"><Eyebrow as="span">{{ $t('arcadia.editor.settings.step') }}</Eyebrow></FieldLabel>
                <div class="flex items-center gap-2 text-sm text-ink-2">
                    <span>{{ $t('arcadia.editor.settings.step_from') }}</span>
                    <Input id="step-min" v-model="form.step_min_minutes" type="number" inputmode="numeric" min="1" max="60" step="1" class="w-20 font-mono"
                           :aria-invalid="form.errors.step_min_minutes ? 'true' : undefined" />
                    <span>{{ $t('arcadia.editor.settings.step_to') }}</span>
                    <Input id="step-max" v-model="form.step_max_minutes" type="number" inputmode="numeric" min="2" max="120" step="1" class="w-20 font-mono"
                           :aria-label="$t('arcadia.editor.settings.step_to')" :aria-invalid="form.errors.step_max_minutes ? 'true' : undefined" />
                    <span>{{ $t('arcadia.editor.settings.step_minutes') }}</span>
                </div>
                <FieldError v-if="form.errors.step_min_minutes || form.errors.step_max_minutes">{{ form.errors.step_min_minutes || form.errors.step_max_minutes }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.settings.step_hint') }}</FieldDescription>
            </Field>

            <Button type="submit" variant="brand" size="lg" class="mt-6" :disabled="form.processing">{{ $t('arcadia.editor.settings.save') }}</Button>
        </form>
    </div>
</template>
