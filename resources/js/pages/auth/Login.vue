<script setup>
/**
 * Arcadia's sign-in. Readers continue with Google — one tap, no form, no password — and come back
 * to the guide they tapped (`Auth\GoogleController`). Fortify's `/login` renders it
 * (`ArcadiaSite::loginPage()`).
 *
 * Email and password, folded under the Google button, is the second way in — the owner's, and
 * since 2026-10-15 any reader's who would rather not use Google: "forgot your password?" sits
 * inside the fold and "create an account" at the end.
 *
 * Inside an app's own browser — WhatsApp, Instagram, Facebook — Google may refuse to sign anyone
 * in, so the page says so first and offers the way out: Chrome on Android, the link to paste
 * elsewhere. Detected from the user agent after mount, so the server-rendered page never guesses.
 *
 * A passkey is the third way in, for an account that added one — offered after a Google sign-in
 * (`PasskeyOffer`) or in Settings › Security — shown only while `PASSKEYS_ENABLED` is on and the
 * browser supports passkeys. On a browser that registered one, it comes first, above Google.
 *
 * It sits in Arcadia's sign-in frame (`ArcadiaAuthFrame`): the landing's photograph, the way back home,
 * the card and the mark.
 */
import { computed, onMounted, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { ChevronDown, ExternalLink, LogIn, TriangleAlert } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaAuthFrame from '@/components/ArcadiaAuthFrame.vue'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import PasskeyVerify from '@/components/PasskeyVerify.vue'
import { passkeyRegisteredHere } from '@/lib/passkey-device'

const props = defineProps({
    pageTitle: String,
    noIndex: Boolean,
    // Arcadia's link preview — every shared guide link previews as this page (`ArcadiaSite::preview()`).
    ogImage: String,
    ogImageAlt: String,
    siteName: String,
    siteDomain: String,
    // Fortify's passkey feature, switched by `PASSKEYS_ENABLED`.
    canUsePasskeys: Boolean,
})

const page = usePage()
const error = computed(() => page.props.message?.error)

// An app's own browser: Android WebViews mark themselves `; wv)`, and the apps name themselves.
const inApp = ref(false)
const android = ref(false)
const copied = ref(false)
// This browser made a passkey: the quickest way back in goes first. Read after mount, like the
// in-app check, so the server-rendered page shows the usual order.
const passkeyFirst = ref(false)

onMounted(() => {
    passkeyFirst.value = props.canUsePasskeys && passkeyRegisteredHere()
    const agent = navigator.userAgent
    inApp.value = /; wv\)|WhatsApp|FBAN|FBAV|Instagram|Line\//i.test(agent)
    android.value = /Android/i.test(agent)
})

// Chrome on Android opens an `intent:` link even from inside another app.
const chromeIntent = computed(() => `intent://${location.host}${location.pathname}${location.search}#Intent;scheme=${location.protocol.replace(':', '')};package=com.android.chrome;end`)

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(location.href)
        copied.value = true
    } catch {
        copied.value = false
    }
}

const showPassword = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/login', {
        onError: () => form.reset('password'),
    })
}
</script>

<template>
    <SeoHead v-bind="props" />

    <ArcadiaAuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('arcadia.auth.heading') }}</h1>
        <p class="mt-1 text-center text-sm text-ink-3">{{ $t('arcadia.auth.lead') }}</p>

        <div v-if="inApp" class="mt-6 rounded-control border border-signal/30 bg-signal/5 p-4" role="alert">
            <p class="m-0 flex items-center gap-2 text-sm font-semibold text-ink">
                <TriangleAlert :size="16" class="shrink-0 text-signal" aria-hidden="true" />
                {{ $t('arcadia.auth.in_app.title') }}
            </p>
            <p class="mt-1 mb-3 text-sm text-ink-3">{{ $t('arcadia.auth.in_app.body') }}</p>
            <div class="flex flex-wrap gap-2">
                <a v-if="android" :href="chromeIntent" class="inline-flex items-center gap-1.5 rounded-control bg-accent px-3 py-2 text-sm font-semibold text-on-accent no-underline">
                    <ExternalLink :size="14" aria-hidden="true" />
                    {{ $t('arcadia.auth.in_app.open') }}
                </a>
                <Button type="button" variant="outline" size="sm" @click="copyLink">{{ $t('arcadia.auth.in_app.copy') }}</Button>
            </div>
            <p v-if="copied" class="mt-2 mb-0 text-xs text-ink-3" role="status">{{ $t('arcadia.auth.in_app.copied') }}</p>
        </div>

        <p v-if="error" class="mt-6 mb-0 rounded-control border border-danger/30 bg-danger/5 p-3 text-sm text-ink" role="alert">{{ error }}</p>

        <PasskeyVerify v-if="passkeyFirst" class="mt-6" :label="$t('arcadia.auth.passkey.sign_in')"
                       :loading-label="$t('arcadia.auth.passkey.signing_in')" :separator="$t('arcadia.auth.passkey.or')" />

        <!-- A plain link, not a fetch: the browser goes to Google and comes back here. Google's
             branding fixes the button's white, its text colour and its four-colour mark. -->
        <a href="/auth/google" :class="passkeyFirst ? '' : 'mt-6'"
           class="flex min-h-12 w-full items-center justify-center gap-3 rounded-control bg-white px-4 py-3 text-base font-semibold text-[#1F1F1F] no-underline shadow-sm hover:bg-white/90">
            <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" />
                <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z" />
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" />
            </svg>
            {{ $t('arcadia.auth.google.continue') }}
        </a>
        <p class="mt-2 mb-0 text-center text-xs text-ink-4">{{ $t('arcadia.auth.google.hint') }}</p>

        <!-- Only for an account that added one in Settings, and only where the browser can. -->
        <PasskeyVerify v-if="canUsePasskeys && !passkeyFirst" class="mt-6" :label="$t('arcadia.auth.passkey.sign_in')"
                       :loading-label="$t('arcadia.auth.passkey.signing_in')" :separator="$t('arcadia.auth.passkey.or')" />

        <button type="button" class="group mx-auto mt-6 flex items-center gap-1 text-xs font-medium text-accent underline-offset-4 decoration-accent/50 transition hover:underline hover:brightness-125 focus-visible:underline"
                :aria-expanded="showPassword" aria-controls="password-sign-in" @click="showPassword = !showPassword">
            {{ $t('arcadia.auth.owner') }}
            <ChevronDown :size="14" aria-hidden="true" class="transition-transform group-hover:translate-y-0.5"
                         :class="showPassword ? 'rotate-180' : ''" />
        </button>

        <form v-show="showPassword || form.errors.email" id="password-sign-in" class="mt-4 flex flex-col gap-5" @submit.prevent="submit">
            <Field :data-invalid="form.errors.email ? true : undefined">
                <FieldLabel for="email">{{ $t('arcadia.auth.fields.email') }}</FieldLabel>
                <Input id="email" v-model="form.email" type="email" autocomplete="email" required :aria-invalid="form.errors.email ? 'true' : undefined" :aria-describedby="form.errors.email ? 'email-error' : undefined" />
                <FieldError v-if="form.errors.email" id="email-error">{{ form.errors.email }}</FieldError>
            </Field>

            <Field>
                <FieldLabel for="password">{{ $t('arcadia.auth.fields.password') }}</FieldLabel>
                <Input id="password" v-model="form.password" type="password" autocomplete="current-password" required />
            </Field>

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <Checkbox id="remember" v-model="form.remember" />
                    <Label for="remember" class="cursor-pointer font-normal text-ink-2">{{ $t('arcadia.auth.fields.remember') }}</Label>
                </div>
                <Link href="/forgot-password" class="text-xs text-ink-3 no-underline hover:text-accent">{{ $t('arcadia.auth.forgot') }}</Link>
            </div>

            <Button type="submit" variant="brand" size="lg" :disabled="form.processing" class="w-full gap-2">
                <span>{{ form.processing ? $t('arcadia.auth.submitting') : $t('arcadia.auth.submit') }}</span>
                <Spinner v-if="form.processing" class="size-5" />
                <LogIn v-else :size="18" />
            </Button>
        </form>

        <p class="mt-6 mb-0 border-t border-rule pt-5 text-center text-sm text-ink-3">
            {{ $t('arcadia.auth.no_account') }}
            <Link href="/register" class="text-accent no-underline hover:underline">{{ $t('arcadia.auth.create_account') }}</Link>
        </p>
    </ArcadiaAuthFrame>
</template>
