<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

// Fortify's passkey routes, written out rather than imported from Wayfinder: they exist only
// while `PASSKEYS_ENABLED` is on, and a build must not depend on that switch.
const confirmOptions = { url: '/passkeys/confirm/options', method: 'get' } as const;
const confirmStore = { url: '/passkeys/confirm', method: 'post' } as const;

defineProps<{ canUsePasskeys?: boolean }>();

</script>

<template>
    <Head :title="$t('arcadia.account.confirm.title')" />

    <!-- Its own heading: `app.js` assigns the layout, so a layout-props object here would be dropped. -->
    <div class="mb-6 space-y-2 text-center">
        <h1 class="text-xl font-medium">{{ $t('arcadia.account.confirm.title') }}</h1>
        <p class="text-sm text-muted-foreground">{{ $t('arcadia.account.confirm.lead') }}</p>
    </div>

    <PasskeyVerify
        v-if="canUsePasskeys"
        :routes="{
            options: confirmOptions,
            submit: confirmStore,
        }"
        :label="$t('arcadia.account.confirm.passkey')"
        :loading-label="$t('arcadia.account.confirm.passkey_loading')"
        :separator="$t('arcadia.account.confirm.or')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{ $t('arcadia.account.confirm.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('arcadia.account.confirm.button') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
