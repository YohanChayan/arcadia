<script setup>
/**
 * Arcadia's photograph — the walker on the hillside at sunset — as a cover behind its positioned
 * parent: the landing's hero and the sign-in frame. Decorative; the parent lays its scrim on top.
 *
 * One wide frame (1376×768, about the hero's own proportion on a desktop), so a wide screen sees
 * nearly all of it — tree, city, walker and sun — rather than a close crop. On a phone `cover`
 * cuts the sides; the anchor at 66% across keeps the walker in. WebP, not indexed PNG: a sunset
 * sky bands in 256 colours (`docs/design-system.md`).
 */
</script>

<template>
    <div aria-hidden="true" class="cover-art absolute inset-0 bg-position-[66%_40%]" :style="{ backgroundImage: 'url(/img/arcadia/hero-wide.webp)' }"></div>
</template>
