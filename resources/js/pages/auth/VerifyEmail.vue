<script setup>
/**
 * "Check your email" — the step between registering on Arcadia and reading a guide. The link in
 * the email opens on Arcadia (`ArcadiaAccountMail`) and comes back to the guide they were going to.
 * Resending is Fortify's `verification.send`, throttled by it.
 */
import { router, useForm } from '@inertiajs/vue3'
import { MailCheck } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaAuthFrame from '@/components/ArcadiaAuthFrame.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    status: { type: String, default: null },
    email: { type: String, default: null },
    pageTitle: String,
    noIndex: Boolean,
})

const form = useForm({})

const resend = () => form.post('/email/verification-notification', { preserveScroll: true })

const signOut = () => router.post('/logout')
</script>

<template>
    <SeoHead :page-title="pageTitle" :no-index="noIndex" />

    <ArcadiaAuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('arcadia.auth.screens.verify-email.title') }}</h1>
        <MailCheck :size="28" class="mx-auto mt-4 block text-accent" aria-hidden="true" />
        <p class="mt-4 mb-0 text-center text-sm text-ink-2">{{ $t('arcadia.auth.screens.verify-email.body', { email: email ?? '' }) }}</p>

        <p v-if="status === 'verification-link-sent'" class="mt-4 mb-0 rounded-control border border-success/30 bg-success/5 p-3 text-center text-sm text-ink" role="status">
            {{ $t('arcadia.auth.screens.verify-email.sent') }}
        </p>

        <Button type="button" variant="brand" size="lg" class="mt-6 w-full" :disabled="form.processing" @click="resend">
            {{ $t('arcadia.auth.screens.verify-email.resend') }}
        </Button>
        <Button type="button" variant="ghost" class="mt-2 w-full" @click="signOut">{{ $t('arcadia.auth.screens.verify-email.other') }}</Button>
    </ArcadiaAuthFrame>
</template>
