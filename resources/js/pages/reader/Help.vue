<script setup>
/**
 * A reader's help: short answers on how Arcadia works, then who to write to. The answers are copy
 * (`arcadia.help.items`), in the order a new reader meets them; the device limit and the contact
 * address arrive from the server, since they differ per account and per deployment.
 */
import { computed } from 'vue'
import { Mail } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import CopyButton from '@/components/common/CopyButton.vue'
import { buttonVariants } from '@/components/ui/button'

const props = defineProps({
    // Null when the deployment sets none: the contact panel is left out.
    contactEmail: { type: String, default: null },
    deviceLimit: { type: Number, required: true },
    pageTitle: String,
    noIndex: Boolean,
})

const seo = computed(() => ({ pageTitle: props.pageTitle, noIndex: props.noIndex }))

const ITEMS = ['open', 'progress', 'notes', 'colours', 'devices', 'account']
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="mx-auto max-w-2xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12">
        <h1 class="m-0 font-display text-2xl font-bold text-ink sm:text-3xl">{{ $t('arcadia.help.title') }}</h1>
        <p class="mt-2 mb-0 text-base text-ink-3">{{ $t('arcadia.help.intro') }}</p>

        <div class="mt-8 flex flex-col gap-3">
            <section v-for="item in ITEMS" :key="item" class="panel p-5">
                <h2 class="m-0 text-base font-semibold text-ink">{{ $t(`arcadia.help.items.${item}.title`) }}</h2>
                <p class="mt-1.5 mb-0 text-sm leading-relaxed text-ink-2">{{ $t(`arcadia.help.items.${item}.body`, { limit: deviceLimit }) }}</p>
            </section>
        </div>

        <section v-if="contactEmail" class="panel mt-8 p-5 sm:p-6">
            <h2 class="m-0 text-base font-semibold text-ink">{{ $t('arcadia.help.contact.title') }}</h2>
            <p class="mt-1.5 mb-0 text-sm text-ink-2">{{ $t('arcadia.help.contact.body') }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a :href="`mailto:${contactEmail}`" :class="buttonVariants({ variant: 'outline' })" class="no-underline">
                    <Mail aria-hidden="true" />
                    {{ contactEmail }}
                </a>
                <CopyButton :value="contactEmail" :label="$t('arcadia.help.contact.copy')" :copied-label="$t('arcadia.help.contact.copied')" />
            </div>
        </section>
    </div>
</template>
