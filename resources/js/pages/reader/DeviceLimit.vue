<script setup>
/**
 * One browser too many (`RecogniseDevice`, option D1): the account already reads Arcadia on as
 * many devices as it may. It lists them and — once every thirty days — lets the reader swap one
 * of them for this browser, landing back where they were going. Past the month's swap, it says to
 * write to the owner, who can free a slot or raise this account's limit. A device unused for
 * ninety days frees its slot by itself, so the list holds only recent ones.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Laptop, LogOut, Mail, Repeat } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaAuthFrame from '@/components/ArcadiaAuthFrame.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    email: { type: String, required: true },
    limit: { type: Number, required: true },
    // [{ id, label, last_used_at }], most recently used first.
    devices: { type: Array, required: true },
    canReplace: { type: Boolean, required: true },
    replaceUrl: { type: String, required: true },
    contactEmail: { type: String, default: null },
    pageTitle: String,
    noIndex: Boolean,
})

const formatDate = iso => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

const replacing = ref(null)

const replace = device => {
    replacing.value = device.id
    router.put(`${props.replaceUrl}/${device.id}`, {}, { onFinish: () => { replacing.value = null } })
}

const signOut = () => router.post('/logout')
</script>

<template>
    <SeoHead :page-title="pageTitle" :no-index="noIndex" />

    <ArcadiaAuthFrame>
        <h1 class="m-0 text-center font-display text-2xl font-semibold text-ink">{{ $t('arcadia.devices.title') }}</h1>
        <Laptop :size="28" class="mx-auto mt-4 block text-accent" aria-hidden="true" />
        <p class="mt-4 mb-0 text-center text-sm text-ink-2">{{ $t('arcadia.devices.lead', { email, count: limit }) }}</p>

        <ul class="m-0 mt-6 divide-y divide-rule rounded-control border border-rule p-0">
            <li v-for="device in devices" :key="device.id" class="flex list-none items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="m-0 truncate text-sm font-medium text-ink">{{ device.label }}</p>
                    <p class="mt-0.5 mb-0 text-xs text-ink-4">{{ $t('arcadia.devices.last_used', { date: formatDate(device.last_used_at) }) }}</p>
                </div>
                <Button v-if="canReplace" type="button" variant="outline" size="sm" class="shrink-0"
                        :disabled="replacing !== null" @click="replace(device)">
                    <Repeat :size="14" aria-hidden="true" />
                    {{ $t('arcadia.devices.replace') }}
                </Button>
            </li>
        </ul>

        <p class="mt-3 mb-0 text-xs text-ink-3">
            {{ $t(canReplace ? 'arcadia.devices.replace_hint' : 'arcadia.devices.replaced_recently') }}
        </p>

        <p v-if="contactEmail" class="mt-4 mb-0 flex items-start gap-2 rounded-control border border-accent/40 bg-accent/10 px-3 py-2.5 text-xs text-ink-2">
            <Mail :size="14" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
            <span>
                {{ $t('arcadia.devices.help') }}
                <a :href="`mailto:${contactEmail}`" class="font-medium text-ink underline underline-offset-2 hover:text-accent">{{ contactEmail }}</a>
            </span>
        </p>

        <Button type="button" variant="ghost" size="lg" class="mt-6 w-full" @click="signOut">
            <LogOut :size="16" aria-hidden="true" />
            {{ $t('arcadia.devices.sign_out') }}
        </Button>
    </ArcadiaAuthFrame>
</template>
