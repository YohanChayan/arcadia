<script setup>
/**
 * The one error page — every status `bootstrap/app.php`'s `respond()` hook renders.
 *
 * Was `errors/404.vue` until 2026-09-24, already generic on its status but named for one of
 * them. Renamed when 403 started reaching real users (manager, developer, a roleless account
 * after sign-in) and 5xx was going to follow. The name is load-bearing in three places that
 * must change together: the handler, `app.js` and `ssr.js`.
 *
 * A status without copy falls back to `errors.*.default`, so adding a status to the handler
 * never renders blank — but give it its own title and description in both locales.
 */
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import { Home, RefreshCw, LogOut } from '@lucide/vue'
import { wTrans } from 'laravel-vue-i18n'
import { Button, buttonVariants } from '@/components/ui/button'

const props = defineProps({
    status: { type: Number, required: true },
    contactEmail: { type: String, default: '' },
})

const KNOWN = [403, 404, 500, 503]
const key = computed(() => (KNOWN.includes(props.status) ? props.status : 'default'))

const title = computed(() => wTrans(`errors.titles.${key.value}`))
const description = computed(() => wTrans(`errors.descriptions.${key.value}`))

// Reloading only helps when the server was at fault; a 403 or 404 reloads into itself.
const canRetry = computed(() => props.status >= 500)

// A refused, signed-in user — a roleless account after sign-in, most of all — otherwise has no
// way out: Home redirects an authenticated user back to the dashboard, which refuses again.
const signedIn = computed(() => !!usePage().props.user)

const reload = () => router.reload()
const signOut = () => router.post(route('logout'))
</script>

<template>
    <div class="min-h-screen w-full bg-small-honey-beehive flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-4xl">
            <div class="mb-6">
                <div class="flex justify-center text-2xl"><ArcadiaMark /></div>
            </div>

            <div class="bg-raised/60 border border-rule rounded-panel overflow-hidden backdrop-blur-[20px] shadow-[var(--panel-shadow)]">
                <div class="bg-raised px-6 py-5 text-center border-b border-edge">
                    <div class="glitch text-brand-gradient-breathe text-6xl sm:text-7xl md:text-8xl font-bold italic" :data-glitch="status">
                        {{ status }}
                    </div>
                    <p class="mt-4 text-2xl sm:text-3xl md:text-4xl text-danger">{{ title }}</p>
                </div>

                <div class="bg-transparent px-6 py-6 text-center">
                    <p class="text-base sm:text-lg md:text-xl text-ink-2 max-w-2xl mx-auto leading-relaxed">
                        {{ description }}
                        {{ $t('errors.common.persistence_message', { email: contactEmail }) }}
                    </p>

                    <div class="mt-7 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <Link href="/" :class="buttonVariants({ variant: 'soft' })" class="no-underline">
                            <Home :size="18" class="text-ink-3" />
                            <span class="text-ink-3 font-semibold">{{ $t('errors.common.home') }}</span>
                        </Link>

                        <Button v-if="canRetry" type="button" variant="soft" @click="reload">
                            <RefreshCw :size="18" class="text-ink-3" />
                            <span class="text-ink-3 font-semibold">{{ $t('errors.common.reload') }}</span>
                        </Button>

                        <Button v-if="signedIn" type="button" variant="soft" @click="signOut">
                            <LogOut :size="18" class="text-ink-3" />
                            <span class="text-ink-3 font-semibold">{{ $t('errors.common.sign_out') }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
