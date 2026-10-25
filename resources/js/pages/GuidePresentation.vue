<script setup>
/**
 * What a guide's link shows someone who may not read it (`GuidePageController`, ADR 0022): its
 * cover, title, level, category, goals and pitch — what it is about, never what is in it — and
 * what this visitor can do, by `state`:
 *
 * - `sign_in` — signed out: sign in to enroll, or to read it if it is theirs;
 * - `open` — an account it is not shared with, while it is open: ask to enroll, with a message;
 * - `pending` — they asked: the owner will write to them;
 * - `closed` — not open for enrollment yet;
 * - `removed` · `declined` — no access, and nothing to ask.
 *
 * No price: payment is arranged with the owner, outside Arcadia. The editor previews it as `open`
 * with `preview`, where nothing is sent.
 */
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Check, Eye, Hourglass, Lock, LogIn, LogOut, Send } from '@lucide/vue'
import SeoHead from '@/components/common/SeoHead.vue'
import ArcadiaPhoto from '@/components/ArcadiaPhoto.vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
    // { title, level, category, goals, pitch, cover: { url, width, height } | null }
    guide: { type: Object, required: true },
    state: { type: String, required: true },
    homeUrl: { type: String, required: true },
    signInUrl: { type: String, default: null },
    email: { type: String, default: null },
    myGuidesUrl: { type: String, default: null },
    accessUrl: { type: String, default: null },
    messageMax: { type: Number, default: 1000 },
    preview: { type: Boolean, default: false },
    pageTitle: String,
    metaDescription: String,
    noIndex: Boolean,
    ogImage: String,
    ogImageAlt: String,
    siteName: String,
    siteDomain: String,
})

const seo = computed(() => ({
    pageTitle: props.pageTitle,
    metaDescription: props.metaDescription,
    noIndex: props.noIndex,
    ogImage: props.ogImage,
    ogImageAlt: props.ogImageAlt,
    siteName: props.siteName,
    siteDomain: props.siteDomain,
}))

const form = useForm({ message: '' })

const ask = () => {
    if (props.preview || !props.accessUrl) {
        return
    }

    form.post(props.accessUrl, { preserveScroll: true })
}

// Sign out, then straight to the sign-in again, so they can pick the other Google account.
const otherAccount = () => router.post(route('logout'), {}, { onFinish: () => router.visit('/login') })

const noAccess = computed(() => ['removed', 'declined'].includes(props.state))
</script>

<template>
    <SeoHead v-bind="seo" />

    <div class="mx-auto max-w-3xl px-4 pb-20 pt-6 sm:px-6 sm:pt-10">
        <p v-if="preview" class="mb-6 flex items-center gap-2 rounded-xl border border-accent/40 bg-raised-solid px-4 py-3 text-sm text-ink">
            <Eye :size="16" class="shrink-0 text-accent" aria-hidden="true" />
            {{ $t('arcadia.presentation.preview') }}
        </p>

        <a :href="homeUrl" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-3 no-underline hover:text-accent">
            <ArrowLeft :size="14" aria-hidden="true" />
            {{ $t('arcadia.presentation.back') }}
        </a>

        <!-- The cover, or Arcadia's own photograph until the owner uploads one. -->
        <img v-if="guide.cover" :src="guide.cover.url" :width="guide.cover.width" :height="guide.cover.height" alt=""
             class="block h-auto w-full rounded-2xl border border-rule">
        <div v-else class="relative aspect-[16/9] overflow-hidden rounded-2xl border border-rule">
            <ArcadiaPhoto />
        </div>

        <header class="mt-8">
            <p class="m-0 flex flex-wrap gap-x-3 gap-y-1 font-mono text-xs uppercase tracking-widest text-accent">
                <span v-if="guide.category">{{ guide.category }}</span>
                <span>{{ guide.level }}</span>
            </p>
            <h1 class="mt-2 mb-0 font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">{{ guide.title }}</h1>
            <p v-if="guide.pitch" class="mt-4 mb-0 text-lg leading-relaxed whitespace-pre-line text-ink-2">{{ guide.pitch }}</p>
        </header>

        <section v-if="guide.goals.length" class="mt-8">
            <h2 class="m-0 text-sm font-semibold text-ink">{{ $t('arcadia.presentation.goals') }}</h2>
            <ul class="m-0 mt-3 flex flex-col gap-2 p-0">
                <li v-for="goal in guide.goals" :key="goal" class="flex list-none items-start gap-2.5 text-base text-ink-2">
                    <Check :size="18" class="mt-0.5 shrink-0 text-accent" aria-hidden="true" />
                    {{ goal }}
                </li>
            </ul>
        </section>

        <!-- What this visitor can do. -->
        <section class="panel mt-10 p-6 sm:p-8" :class="noAccess ? '' : 'border-accent/30'">
            <template v-if="state === 'sign_in'">
                <h2 class="m-0 font-display text-xl font-semibold text-ink">{{ $t('arcadia.presentation.sign_in.title') }}</h2>
                <p class="mt-2 mb-0 text-sm text-ink-3">{{ $t('arcadia.presentation.sign_in.body') }}</p>
                <a :href="signInUrl" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-on-accent no-underline sm:w-auto">
                    <LogIn :size="16" aria-hidden="true" />
                    {{ $t('arcadia.presentation.sign_in.action') }}
                </a>
            </template>

            <form v-else-if="state === 'open'" @submit.prevent="ask">
                <h2 class="m-0 font-display text-xl font-semibold text-ink">{{ $t('arcadia.presentation.open.title') }}</h2>
                <p class="mt-2 mb-4 text-sm text-ink-3">{{ $t('arcadia.presentation.open.body') }}</p>
                <Textarea v-model="form.message" rows="3" :maxlength="messageMax" :disabled="preview"
                          :placeholder="$t('arcadia.presentation.open.message_placeholder')" :aria-label="$t('arcadia.presentation.open.message')" />
                <p v-if="form.errors.message" class="mt-1 mb-0 text-xs text-danger">{{ form.errors.message }}</p>
                <Button type="submit" variant="brand" size="cta" class="mt-4 w-full sm:w-auto" :disabled="form.processing || preview">
                    <Send :size="16" aria-hidden="true" />
                    {{ $t('arcadia.presentation.open.action') }}
                </Button>
                <p class="mt-3 mb-0 text-xs text-ink-4">{{ $t('arcadia.presentation.open.hint') }}</p>
            </form>

            <div v-else class="flex items-start gap-3" role="status">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-raised-solid text-accent">
                    <Hourglass v-if="state === 'pending' || state === 'closed'" :size="18" aria-hidden="true" />
                    <Lock v-else :size="18" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2 class="m-0 font-display text-lg font-semibold text-ink">{{ $t(`arcadia.presentation.${state}.title`) }}</h2>
                    <p class="mt-1 mb-0 text-sm text-ink-3">{{ $t(`arcadia.presentation.${state}.body`) }}</p>
                </div>
            </div>

            <!-- Which account this is — most often the wrong one of several Google accounts. -->
            <div v-if="email" class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-rule pt-4 text-xs text-ink-4">
                <span class="min-w-0 truncate">{{ $t('arcadia.presentation.signed_in_as', { email }) }}</span>
                <a v-if="myGuidesUrl" :href="myGuidesUrl" class="text-ink-3 no-underline hover:text-accent">{{ $t('arcadia.presentation.my_guides') }}</a>
                <button type="button" class="inline-flex items-center gap-1 text-ink-3 hover:text-accent" @click="otherAccount">
                    <LogOut :size="12" aria-hidden="true" />
                    {{ $t('arcadia.presentation.other_account') }}
                </button>
            </div>
        </section>
    </div>
</template>
