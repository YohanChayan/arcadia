<script setup>
/**
 * Arcadia's public shell, on `arcadia.` — chosen by `shellFor` for any
 * `*` page. No shared navbar, no footer, no particles. A guides
 * page draws its own header, because until launch there is only the one page and nothing to
 * navigate between.
 */
</script>

<template>
    <!-- `overflow-x-clip`, not `overflow-hidden`: it still crops what bleeds sideways (the
         landing's backdrops), but hidden makes this a scroll container, and anything sticky
         inside — the guide page's notes panel — then sticks to it instead of the screen. -->
    <main class="relative min-h-screen overflow-x-clip" role="main">
        <slot></slot>
    </main>
</template>
