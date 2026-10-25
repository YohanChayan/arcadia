<script setup>
/**
 * "Forgot your password?" for an email-and-password account on Arcadia. The link it emails opens
 * on Arcadia (`ArcadiaAccountMail`). Fortify answers the same whether the address exists or not.
 */
import { useForm } from '@inertiajs/vue3'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaAuthFrame from '@/components/ArcadiaAuthFrame.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

const props = defineProps({
    status: { type: String, default: null },
    pageTitle: String,
    noIndex: Boolean,
})

const form = useForm({ email: '' })

const submit = () => form.post('/forgot-password')
</script>

<template>
    <SeoHead :page-title="pageTitle" :no-index="noIndex" />

    <ArcadiaAuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('arcadia.auth.screens.forgot-password.title') }}</h1>
        <p class="mt-1 text-center text-sm text-ink-3">{{ $t('arcadia.auth.screens.forgot-password.lead') }}</p>
        <p v-if="status" class="mt-6 mb-0 rounded-control border border-success/30 bg-success/5 p-3 text-sm text-ink" role="status">{{ status }}</p>

        <form class="mt-6 flex flex-col gap-5" @submit.prevent="submit">
            <Field :data-invalid="form.errors.email ? true : undefined">
                <FieldLabel for="email">{{ $t('arcadia.auth.fields.email') }}</FieldLabel>
                <Input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" required :aria-invalid="form.errors.email ? 'true' : undefined" />
                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
            </Field>

            <Button type="submit" variant="brand" size="lg" :disabled="form.processing" class="w-full">{{ $t('arcadia.auth.screens.forgot-password.submit') }}</Button>
        </form>
    </ArcadiaAuthFrame>
</template>
