<script setup>
/**
 * One confirmation dialog per screen, not one per row.
 *
 * Users/Index once rendered a full modal inside every `<tr>` and tracked its open state by
 * writing a `dialog` property onto the user object returned by the API — so the flag lived
 * on server data and was wiped by the next fetch.
 *
 * Built on `AlertDialog`, which traps focus, returns it to the trigger, and cannot be
 * dismissed by a stray click outside. The hand-rolled version it replaced had none of that.
 * The confirm button is a plain `Button` rather than `AlertDialogAction`, because the action
 * closes the dialog on click — and the caller closes it only once the request succeeds.
 */
import { AlertTriangle } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    body: { type: String, default: '' },
    confirmLabel: { type: String, required: true },
    processing: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

// Escape and the Cancel button both arrive here as `open` becoming false.
const onOpenChange = isOpen => { if (!isOpen) emit('cancel') }
</script>

<template>
    <AlertDialog :open="open" @update:open="onOpenChange">
        <AlertDialogContent class="max-w-md">
            <div class="flex gap-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-danger/15">
                    <AlertTriangle :size="20" class="text-danger" />
                </span>
                <div class="min-w-0">
                    <AlertDialogTitle class="font-display text-lg font-semibold text-ink">{{ title }}</AlertDialogTitle>
                    <AlertDialogDescription :class="body ? 'mt-1 text-sm text-ink-3' : 'sr-only'">{{ body || title }}</AlertDialogDescription>
                </div>
            </div>

            <AlertDialogFooter class="mt-2">
                <AlertDialogCancel :disabled="processing">{{ $t('admin.confirm.cancel') }}</AlertDialogCancel>
                <Button variant="destructive" :disabled="processing" @click="emit('confirm')">
                    <Spinner v-if="processing" />
                    {{ confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
