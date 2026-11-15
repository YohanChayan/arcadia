<script setup>
/**
 * An email-and-password account on Arcadia — the second way in, beside Google (2026-10-15).
 * Anyone may create one; it reads no guide until a guide is shared with its email and the email
 * is verified, which is the next screen. Fortify's `/register`
 * (`Site::authPage()`).
 */
import { Link, useForm } from '@inertiajs/vue3'
import SeoHead from '@/components/common/SeoHead.vue'
import AuthFrame from '@/components/AuthFrame.vue'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'

const props = defineProps({
    pageTitle: String,
    noIndex: Boolean,
})

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
})
</script>

<template>
    <SeoHead v-bind="props" />

    <AuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('auth.screens.register.title') }}</h1>
        <p class="mt-1 text-center text-sm text-ink-3">{{ $t('auth.screens.register.lead') }}</p>
        <form class="mt-6 flex flex-col gap-5" @submit.prevent="submit">
            <Field :data-invalid="form.errors.name ? true : undefined">
                <FieldLabel for="name">{{ $t('auth.fields.name') }}</FieldLabel>
                <Input id="name" v-model="form.name" autocomplete="name" required :aria-invalid="form.errors.name ? 'true' : undefined" />
                <FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError>
            </Field>

            <Field :data-invalid="form.errors.email ? true : undefined">
                <FieldLabel for="email">{{ $t('auth.fields.email') }}</FieldLabel>
                <Input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" required :aria-invalid="form.errors.email ? 'true' : undefined" />
                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
                <FieldDescription v-else>{{ $t('auth.screens.register.email_hint') }}</FieldDescription>
            </Field>

            <Field :data-invalid="form.errors.password ? true : undefined">
                <FieldLabel for="password">{{ $t('auth.fields.password') }}</FieldLabel>
                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required :aria-invalid="form.errors.password ? 'true' : undefined" />
                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
            </Field>

            <Field>
                <FieldLabel for="password_confirmation">{{ $t('auth.fields.password_confirmation') }}</FieldLabel>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </Field>

            <Button type="submit" variant="brand" size="lg" :disabled="form.processing" class="w-full gap-2">
                <span>{{ $t('auth.screens.register.submit') }}</span>
                <Spinner v-if="form.processing" class="size-5" />
            </Button>
        </form>

        <p class="mt-6 mb-0 text-center text-sm text-ink-3">
            {{ $t('auth.screens.register.have_account') }}
            <Link href="/login" class="text-accent no-underline hover:underline">{{ $t('auth.submit') }}</Link>
        </p>
    </AuthFrame>
</template>
