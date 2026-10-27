<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { defaultPasskeyName, rememberPasskeyRegistered } from '@/lib/passkey-device';

const emit = defineEmits<{
    success: [];
}>();

const name = ref(defaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        rememberPasskeyRegistered();
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-sm text-muted-foreground">
        {{ $t('arcadia.account.passkeys.unsupported') }}
    </div>

    <Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        {{ $t('arcadia.account.passkeys.add') }}
    </Button>

    <form
        v-else
        @submit="handleSubmit"
        class="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
        <div class="grid gap-2">
            <Label for="passkey-name">{{ $t('arcadia.account.passkeys.name') }}</Label>
            <Input
                id="passkey-name"
                type="text"
                v-model="name"
                :placeholder="$t('arcadia.account.passkeys.name_placeholder')"
                class="mt-1 block w-full border-foreground/20"
                v-focus
            />
            <p class="text-xs text-muted-foreground">
                {{ $t('arcadia.account.passkeys.name_hint') }}
            </p>
        </div>

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <Button type="submit" :disabled="isLoading || !name.trim()">
                {{ isLoading ? $t('arcadia.account.passkeys.registering') : $t('arcadia.account.passkeys.register') }}
            </Button>
            <Button type="button" variant="ghost" @click="handleCancel">
                {{ $t('arcadia.account.cancel') }}
            </Button>
        </div>
    </form>
</template>
