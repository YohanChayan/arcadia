<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    status?: string | null;
} & ManagePasskeysProps;

const props = defineProps<Props>();

</script>

<template>
    <Head :title="$t('arcadia.account.nav.security')" />

    <h1 class="sr-only">{{ $t('arcadia.account.nav.security') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('arcadia.account.password.title')"
            :description="$t('arcadia.account.password.lead')"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">{{ $t('arcadia.account.password.current') }}</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="$t('arcadia.account.password.current')"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('arcadia.account.password.new') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="$t('arcadia.account.password.new')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{ $t('arcadia.account.password.confirm') }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="$t('arcadia.account.password.confirm')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    {{ $t('arcadia.account.save') }}
                </Button>
                <p v-if="props.status === 'password-updated'" class="text-sm text-muted-foreground" role="status">
                    {{ $t('arcadia.account.password.saved') }}
                </p>
            </div>
        </Form>
    </div>

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
