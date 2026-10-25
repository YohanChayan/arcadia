<script setup>
import { useId } from 'vue'

/**
 * Arcadia's mark — the "A": two legs in the logo's blues and a leaf across them, teal → mint — and,
 * unless `glyph`, the name beside it in white Sora, as the mock sets it. The only place the mark
 * is drawn: the landing's nav, the
 * sign-in and the editor's sidebar all render this.
 *
 * The glyph is the vector mark, `img/arcadia/mark.svg` (the A, 2026-10-21): its viewBox cut to the
 * painted shapes, 1063 × 963, so no dead canvas sits around it. An `<img>`, not inline, so its
 * gradient ids can never collide with another mark on the page.
 *
 * Sized by the font size it sits in. Beside the name the mark is Sora's cap height tall (730 of
 * 1000 units, 0.73em), so it stands as high as the name's "A"; centred on the 1em line, it sits
 * within 0.03em of the capitals, which a baseline alignment would not do reliably — the name is
 * `truncate`, and an overflow box's baseline is its bottom edge. Alone, it is 1.15em tall.
 * `text-2xl` on the root sizes both. A place that wants the mark to lead sets `--mark-height` on
 * the root — the landing's nav does, at 1.1em.
 *
 * The one detail in the name (2026-10-21): the "i" wears the mark's leaf in place of its dot,
 * drawn from the leaf's own path in `mark.svg`. The letter stays a real "i", so the name copies,
 * searches and reads aloud as "Arcadia": its dot is painted out (`.tittle`) and the leaf is an
 * inline, `aria-hidden` SVG laid over it. Inline so it takes the theme's colours; its ids come
 * from `useId()`, so two marks on one page never share a gradient.
 */
defineProps({
    // The glyph alone — the sign-in's card, a collapsed sidebar.
    glyph: { type: Boolean, default: false },
})

const id = useId()

// The leaf of `public/img/arcadia/mark.svg`, in its own coordinates: the stem at bottom-left.
const LEAF = 'M 430 855 C 436 770 468 705 525 662 C 600 606 700 590 803 566 C 776 655 714 735 620 775 C 560 800 505 808 470 820 C 452 827 440 838 430 855 Z'
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-[0.35em] font-display font-bold leading-none tracking-tight text-ink">
        <img src="/img/arcadia/mark.svg" :alt="glyph ? 'Arcadia' : ''" width="1063" height="963" class="block w-auto shrink-0" :class="glyph ? 'h-[1.15em]' : 'h-[var(--mark-height,0.73em)]'" />
        <span v-if="!glyph" class="truncate">Arcad<span class="tittle">i<svg class="leaf" viewBox="430 566 373 289" aria-hidden="true">
            <defs>
                <linearGradient :id="`${id}-top`" x1="460" y1="820" x2="790" y2="570" gradientUnits="userSpaceOnUse">
                    <stop offset="0" style="stop-color: var(--aurora-4)" />
                    <stop offset="1" style="stop-color: var(--aurora-5)" />
                </linearGradient>
                <linearGradient :id="`${id}-under`" x1="450" y1="830" x2="720" y2="660" gradientUnits="userSpaceOnUse">
                    <stop offset="0" style="stop-color: var(--aurora-2)" />
                    <stop offset="1" style="stop-color: var(--aurora-3)" />
                </linearGradient>
                <clipPath :id="`${id}-clip`"><path :d="LEAF" /></clipPath>
            </defs>
            <path :d="LEAF" :fill="`url(#${id}-top)`" />
            <path :clip-path="`url(#${id}-clip)`" :fill="`url(#${id}-under)`" d="M 430 856 C 470 770 560 700 690 662 C 740 648 800 600 820 560 L 900 900 L 380 900 Z" />
        </svg></span>a</span>
    </span>
</template>

<style scoped>
/* Sora Bold's "i": the stem stops at 548 units and the dot spans 602–769, in a 1260-unit content
   box whose top sits 970 above the baseline, and the stem is centred 0.14em in. So the gap
   between dot and stem falls at (970 − 575) / 1260 ≈ 31% from the top: above it the letter is
   painted out, below it the ink. A fallback font only loses the top of its "i". */
.tittle {
    position: relative;
    color: transparent;
    background: linear-gradient(to bottom, transparent 31.3%, var(--color-ink) 31.3%);
    -webkit-background-clip: text;
    background-clip: text;
}

/* The leaf grows from the stem's top: its stem point at 0.58em above the baseline, just over the
   stem, and it leans up and right over the "a", which stops lower, at x-height. 0.30em wide, at
   the leaf's own 373 × 289. */
.leaf {
    position: absolute;
    left: 0.13em;
    top: 0.158em;
    width: 0.3em;
    height: 0.2325em;
    overflow: visible;
    pointer-events: none;
}
</style>
