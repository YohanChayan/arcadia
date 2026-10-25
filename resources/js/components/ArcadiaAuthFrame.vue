<script setup>
/**
 * The frame around Arcadia's sign-in pages (2026-10-15): the landing's photograph, veiled deep
 * enough that the card reads on it, a way back home above the card, and the card itself with
 * Arcadia's mark. **Nothing inside it** — the form is the page's, in the default slot, so the
 * sign-in and a client registration built later drop in unchanged.
 */
import { Link } from '@inertiajs/vue3'
import { ArrowLeft } from '@lucide/vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import ArcadiaPhoto from '@/components/ArcadiaPhoto.vue'
</script>

<template>
    <div class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
        <div aria-hidden="true" class="absolute inset-0 -z-10">
            <ArcadiaPhoto />
            <div class="absolute inset-0 bg-ground/80"></div>
        </div>

        <div class="w-full max-w-sm">
            <Link :href="`/${$page.props.locale}`" class="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-2 no-underline hover:text-accent">
                <ArrowLeft :size="16" />
                {{ $t('arcadia.auth.back') }}
            </Link>

            <div class="panel p-6 backdrop-blur-md sm:p-8">
                <ArcadiaMark glyph class="mx-auto mb-5 flex w-fit text-5xl" />
                <slot />
            </div>
        </div>
    </div>
</template>
