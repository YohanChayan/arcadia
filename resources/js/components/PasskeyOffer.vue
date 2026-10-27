<script setup>
/**
 * After a Google sign-in, offer a passkey so the next sign-in is the device's unlock instead of
 * Google. The server says when — once, to an account with none (`offerPasskey`); the browser says
 * whether this device can hold one and whether the reader already said no here.
 *
 * The sign-in counted as a password confirmation, so adding it needs no password.
 */
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePasskeyRegister } from '@laravel/passkeys/vue'
import { toast } from 'vue-sonner'
import { trans } from 'laravel-vue-i18n'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { canHoldPasskey, declineOffer, defaultPasskeyName, offerDeclined, rememberPasskeyRegistered } from '@/lib/passkey-device'

const page = usePage()
const open = ref(false)

const { register, isLoading, error } = usePasskeyRegister({
    onSuccess: () => {
        rememberPasskeyRegistered()
        open.value = false
        toast.success(trans('arcadia.account.passkeys.offer.added'))
    },
})

watch(() => page.props.offerPasskey, async offer => {
    if (offer && !offerDeclined() && await canHoldPasskey()) {
        open.value = true
    }
}, { immediate: true })

const add = () => register(defaultPasskeyName() || trans('arcadia.account.passkeys.title'))

const decline = forGood => {
    declineOffer(forGood)
    open.value = false
}
</script>

<template>
    <Sheet :open="open" @update:open="value => !value && decline(false)">
        <SheetContent side="bottom" class="mx-auto max-w-md rounded-t-2xl p-6 sm:bottom-6 sm:rounded-2xl sm:border">
            <SheetHeader class="p-0">
                <SheetTitle>{{ $t('arcadia.account.passkeys.offer.title') }}</SheetTitle>
                <SheetDescription>{{ $t('arcadia.account.passkeys.offer.lead') }}</SheetDescription>
            </SheetHeader>

            <InputError v-if="error" :message="error" />

            <SheetFooter class="flex-col gap-2 p-0">
                <Button type="button" variant="brand" class="w-full" :disabled="isLoading" @click="add">
                    {{ isLoading ? $t('arcadia.account.passkeys.registering') : $t('arcadia.account.passkeys.offer.add') }}
                </Button>
                <Button type="button" variant="outline" class="w-full" :disabled="isLoading" @click="decline(false)">
                    {{ $t('arcadia.account.passkeys.offer.later') }}
                </Button>
                <Button type="button" variant="ghost" size="sm" class="w-full text-ink-3" :disabled="isLoading" @click="decline(true)">
                    {{ $t('arcadia.account.passkeys.offer.never') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
