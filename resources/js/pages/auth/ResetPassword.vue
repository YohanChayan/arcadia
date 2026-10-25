<script setup>
/**
 * A new password for an email-and-password account on Arcadia, from the emailed link. On success
 * Fortify sends them to sign in, with a status.
 */
import { useForm } from '@inertiajs/vue3'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaAuthFrame from '@/components/ArcadiaAuthFrame.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
    pageTitle: String,
    noIndex: Boolean,
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
})
</script>

<template>
    <SeoHead :page-title="pageTitle" :no-index="noIndex" />

    <ArcadiaAuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('arcadia.auth.screens.reset-password.title') }}</h1>
        <form class="mt-6 flex flex-col gap-5" @submit.prevent="submit">
            <Field :data-invalid="form.errors.email ? true : undefined">
                <FieldLabel for="email">{{ $t('arcadia.auth.fields.email') }}</FieldLabel>
                <Input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="form.errors.email ? 'true' : undefined" />
                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
            </Field>

            <Field :data-invalid="form.errors.password ? true : undefined">
                <FieldLabel for="password">{{ $t('arcadia.auth.fields.password') }}</FieldLabel>
                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required :aria-invalid="form.errors.password ? 'true' : undefined" />
                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
            </Field>

            <Field>
                <FieldLabel for="password_confirmation">{{ $t('arcadia.auth.fields.password_confirmation') }}</FieldLabel>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </Field>

            <Button type="submit" variant="brand" size="lg" :disabled="form.processing" class="w-full">{{ $t('arcadia.auth.screens.reset-password.submit') }}</Button>
        </form>
    </ArcadiaAuthFrame>
</template>
