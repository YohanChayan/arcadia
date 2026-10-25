<script setup>
/**
 * The guide hosts' backdrop: large, soft, blurred lights in the guides' hues (`--aurora-*`).
 * Purely decorative, it sits behind its positioned parent and carries no text, so the lights are
 * free to be bright.
 *
 * `hero` is three lights over a dot grid that fades out from the top — the landing's hero and the
 * sign-in page. `band` is two quieter lights at the ends, for a strip whose text sits in the middle.
 */
defineProps({
    variant: { type: String, default: 'hero', validator: value => ['hero', 'band'].includes(value) },
})
</script>

<template>
    <div aria-hidden="true" class="aurora-field pointer-events-none absolute -z-10 overflow-hidden" :class="variant === 'hero' ? 'inset-x-0 top-0 h-[56rem]' : 'inset-0'">
        <template v-if="variant === 'hero'">
            <span class="aurora aurora-1"></span>
            <span class="aurora aurora-2"></span>
            <span class="aurora aurora-3"></span>
            <span class="dots absolute inset-0"></span>
        </template>
        <template v-else>
            <span class="aurora aurora-2 aurora-low"></span>
            <span class="aurora aurora-4 aurora-low"></span>
        </template>
    </div>
</template>

<style scoped>
.aurora {
    position: absolute;
    width: 42rem;
    height: 42rem;
    border-radius: 9999px;
    filter: blur(110px);
    opacity: 0.38;
}
.aurora-1 { top: -16rem; left: -10rem; background: var(--aurora-1); }
.aurora-2 { top: -6rem; right: -14rem; background: var(--aurora-2); opacity: 0.28; }
.aurora-3 { top: 18rem; left: 30%; width: 30rem; height: 30rem; background: var(--aurora-3); opacity: 0.16; }
.aurora-4 { background: var(--aurora-4); }
/* In a band: two lights at its ends, quieter, so the text in the middle stays clear. */
.aurora-low { top: 50%; width: 32rem; height: 22rem; margin-top: -11rem; opacity: 0.2; }
.aurora-low.aurora-2 { left: -12rem; right: auto; }
/* The green a little larger than its pair, and further in (2026-10-14). */
.aurora-low.aurora-4 { right: -10rem; width: 38rem; height: 26rem; margin-top: -13rem; }

/* On a phone the lights fill most of the screen at once, so they are dimmed there; from `sm`
   up they spread over more ground and take their full strength. */
.aurora-field {
    opacity: 0.65;
}
@media (min-width: 640px) {
    .aurora-field {
        opacity: 1;
    }
}

/* A faint grid of dots, strongest at the top and gone further down. */
.dots {
    background-image: radial-gradient(color-mix(in oklab, var(--ink) 14%, transparent) 1px, transparent 1px);
    background-size: 22px 22px;
    mask-image: radial-gradient(70% 60% at 50% 20%, black, transparent);
}

/* The lights drift slowly; decoration only, so never under reduced motion. */
@media (prefers-reduced-motion: no-preference) {
    .aurora {
        animation: drift 22s ease-in-out infinite alternate;
    }
    .aurora-2 { animation-duration: 28s; animation-direction: alternate-reverse; }
    .aurora-3 { animation-duration: 34s; }
}

@keyframes drift {
    to {
        transform: translate(4rem, 3rem) scale(1.12);
    }
}
</style>
