<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import type { Passkey } from '@/types/auth';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    // Written out, not imported from Wayfinder: the route exists only while passkeys are on.
    router.delete(`/user/passkeys/${id}`, {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <Heading
            variant="small"
            :title="$t('arcadia.account.passkeys.title')"
            :description="$t('arcadia.account.passkeys.lead')"
        />

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <KeyRound class="h-7 w-7 text-muted-foreground" />
                </div>
                <p class="font-medium">{{ $t('arcadia.account.passkeys.empty') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ $t('arcadia.account.passkeys.empty_hint') }}</p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
