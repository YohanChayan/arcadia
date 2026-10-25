<script setup>
/**
 * The landing page's one action: ask for a guide, in a popup built for someone who will not
 * fill in a form. One line for what to learn — or a tap on an idea — and one contact field that
 * takes a WhatsApp number or an email, told apart on the server. "What for" was asked here until
 * 2026-10-16 and dropped as one tap too many; older requests still carry it.
 *
 * Asking needs no account (decided 2026-10-16). A signed-in visitor is shown the account the
 * request is recorded with — the one the guide will be assigned to.
 *
 * It posts to the host (`InquiryController`), which stores the request for the editor and mails
 * the owner; the popup then thanks them in place, repeating the contact they typed so a mistake
 * is caught there, with Arcadia's own address to write to. Opened by the page, already holding a
 * topic when a topic tile opened it.
 */
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Mail, MessageCircle, PartyPopper, Send, UserRound } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

const props = defineProps({
    url: { type: String, required: true },
    // The signed-in account the request is recorded with — `{ name, email }` — or null.
    requester: { type: Object, default: null },
    // The topic it opens with: a tile's name, or nothing.
    topic: { type: String, default: '' },
    // One-tap ideas for what to learn.
    suggestions: { type: Array, default: () => [] },
    // What a guide costs, already written: `$3` — or null, and the lead names no price.
    price: { type: String, default: null },
    // The owner's note beside it: `BCV`, or none.
    priceNote: { type: String, default: null },
    // Arcadia's own address, for someone who typed their contact wrong. None hides the note.
    contactEmail: { type: String, default: null },
})

const open = defineModel('open', { type: Boolean, default: false })

const form = useForm({ topic: '', contact: '', website: '' })

const sent = ref(false)

// The same rule the server files it by (`Inquiry::saving`): an `@` makes it an email.
const contactIsEmail = computed(() => form.contact.includes('@'))

// Each opening starts from the topic it was opened with; a thank-you is not shown twice.
watch(open, isOpen => {
    if (!isOpen) {
        return
    }

    if (sent.value) {
        form.reset()
        sent.value = false
    }

    form.clearErrors()
    if (props.topic) {
        form.topic = props.topic
    }
})

const submit = () => {
    form.post(props.url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            sent.value = true
        },
    })
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-[calc(100%-2rem)] max-w-md border-rule p-0">
            <div aria-hidden="true" class="glow pointer-events-none absolute inset-x-0 top-0 h-40"></div>

            <div v-if="sent" class="relative flex flex-col items-center px-6 py-10 text-center">
                <span class="flex size-14 items-center justify-center rounded-full bg-accent text-on-accent shadow-[var(--glow)]">
                    <PartyPopper :size="26" aria-hidden="true" />
                </span>
                <DialogTitle class="mt-5 font-display text-2xl font-bold text-ink">{{ $t('arcadia.landing.ask.done.title') }}</DialogTitle>
                <DialogDescription class="mt-2 text-ink-3">{{ $t('arcadia.landing.ask.done.body') }}</DialogDescription>

                <!-- Where they will be reached, so a wrong number or address is caught here, and where to write if it is. -->
                <div class="mt-6 w-full divide-y divide-rule rounded-control border border-rule text-left">
                    <div class="px-4 py-3">
                        <p class="m-0 text-xs text-ink-4">{{ $t(contactIsEmail ? 'arcadia.landing.ask.done.to_email' : 'arcadia.landing.ask.done.to_whatsapp') }}</p>
                        <p class="mt-1 mb-0 flex items-center gap-2 font-medium break-all text-ink">
                            <component :is="contactIsEmail ? Mail : MessageCircle" :size="16" class="shrink-0 text-accent" aria-hidden="true" />
                            {{ form.contact }}
                        </p>
                    </div>
                    <div v-if="requester" class="px-4 py-3">
                        <p class="m-0 text-xs text-ink-4">{{ $t('arcadia.landing.ask.done.to_account') }}</p>
                        <p class="mt-1 mb-0 flex items-center gap-2 font-medium break-all text-ink">
                            <UserRound :size="16" class="shrink-0 text-accent" aria-hidden="true" />
                            {{ requester.email }}
                        </p>
                    </div>
                </div>
                <p v-if="contactEmail" class="mt-3 mb-0 flex w-full items-start gap-2 rounded-control border border-accent/40 bg-accent/10 px-3 py-2.5 text-left text-xs text-ink-2">
                    <Mail :size="14" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
                    <span>
                        {{ $t('arcadia.landing.ask.done.wrong') }}
                        <a :href="`mailto:${contactEmail}`" class="font-medium text-ink underline underline-offset-2 hover:text-accent">{{ contactEmail }}</a>
                    </span>
                </p>

                <Button type="button" variant="brand" size="lg" class="mt-7" @click="open = false">{{ $t('arcadia.landing.ask.done.close') }}</Button>
            </div>

            <form v-else class="relative max-h-[85vh] overflow-y-auto px-6 pt-7 pb-6" @submit.prevent="submit">
                <DialogHeader class="text-left">
                    <DialogTitle class="font-display text-2xl font-bold text-ink">{{ $t('arcadia.landing.ask.title') }}</DialogTitle>
                    <DialogDescription class="text-ink-3">{{ price ? $t('arcadia.landing.ask.lead', { price: priceNote ? `${price} ${priceNote}` : price }) : $t('arcadia.landing.ask.lead_unpriced') }}</DialogDescription>
                </DialogHeader>

                <div class="mt-6 space-y-5">
                    <Field :data-invalid="form.errors.topic ? true : undefined">
                        <FieldLabel for="ask-topic">{{ $t('arcadia.landing.ask.topic') }}</FieldLabel>
                        <Input id="ask-topic" v-model="form.topic" type="text" maxlength="200" autocomplete="off"
                               :placeholder="$t('arcadia.landing.ask.topic_placeholder')"
                               :aria-invalid="form.errors.topic ? 'true' : undefined" />
                        <FieldError v-if="form.errors.topic">{{ form.errors.topic }}</FieldError>

                        <p class="mt-2 mb-1.5 text-xs text-ink-4">{{ $t('arcadia.landing.ask.suggestions') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="idea in suggestions" :key="idea" type="button"
                                    class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                                    :class="form.topic === idea ? 'border-accent bg-accent/15 text-ink' : 'border-rule text-ink-3 hover:border-accent hover:text-ink'"
                                    @click="form.topic = idea">
                                {{ idea }}
                            </button>
                        </div>
                    </Field>

                    <Field :data-invalid="form.errors.contact ? true : undefined">
                        <FieldLabel for="ask-contact">{{ $t('arcadia.landing.ask.contact') }}</FieldLabel>
                        <Input id="ask-contact" v-model="form.contact" type="text" inputmode="email" maxlength="160" autocomplete="email"
                               :placeholder="$t('arcadia.landing.ask.contact_placeholder')"
                               :aria-invalid="form.errors.contact ? 'true' : undefined" />
                        <FieldError v-if="form.errors.contact">{{ form.errors.contact }}</FieldError>
                        <FieldDescription v-else>{{ $t('arcadia.landing.ask.contact_hint') }}</FieldDescription>
                    </Field>

                    <!-- Signed in: the account the request is recorded with, the one the guide is assigned to. -->
                    <div v-if="requester" class="rounded-control border border-rule px-3 py-2.5">
                        <p class="m-0 text-xs text-ink-4">{{ $t('arcadia.landing.ask.as_account') }}</p>
                        <p class="mt-0.5 mb-0 flex items-center gap-2 text-sm font-medium break-all text-ink">
                            <UserRound :size="14" class="shrink-0 text-accent" aria-hidden="true" />
                            {{ requester.email }}
                        </p>
                    </div>

                    <!-- For bots only: hidden from people and from screen readers. -->
                    <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
                           class="absolute -left-[9999px] h-px w-px opacity-0">
                </div>

                <p v-if="contactEmail" class="mt-5 mb-0 flex items-start gap-2 rounded-control border border-accent/40 bg-accent/10 px-3 py-2.5 text-xs text-ink-2">
                    <Mail :size="14" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
                    <span>
                        {{ $t('arcadia.landing.ask.help') }}
                        <a :href="`mailto:${contactEmail}`" class="font-medium text-ink underline underline-offset-2 hover:text-accent">{{ contactEmail }}</a>
                    </span>
                </p>

                <Button type="submit" variant="brand" size="lg" class="mt-7 w-full" :disabled="form.processing">
                    <Send :size="18" aria-hidden="true" />
                    {{ $t('arcadia.landing.ask.send') }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* The page's light, carried into the popup. */
.glow {
    background: radial-gradient(70% 100% at 50% 0%, color-mix(in oklab, var(--aurora-1) 35%, transparent), transparent 70%);
}
</style>
