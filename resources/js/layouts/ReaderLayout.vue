<script setup>
/**
 * A signed-in reader's shell on Arcadia — chosen by `shellFor` for "My guides", help, and a guide
 * the reader opens. One slim bar above the page: the mark, which leads to "My guides", then
 * "My guides", "Help" and signing out; under it, the page as `PublicLayout` would hold it. The
 * bar is not sticky, so it never covers the guide's notes panel, which sticks to the top.
 *
 * Route names carry the language (`es.reader.home`), so the links follow the page's.
 */
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { BookOpen, LifeBuoy, LogOut } from '@lucide/vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import { Button } from '@/components/ui/button'

const page = usePage()

const links = computed(() => [
    { key: 'my_guides', route: `${page.props.locale}.reader.home`, icon: BookOpen },
    { key: 'help', route: `${page.props.locale}.reader.help`, icon: LifeBuoy },
].map(link => ({ ...link, href: route(link.route, {}, false) })))

const current = href => page.url.split('?')[0] === href

const signOut = () => router.post(route('logout'))
</script>

<template>
    <header class="border-b border-rule">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6" :aria-label="$t('arcadia.reader_nav.label')">
            <Link :href="links[0].href" class="no-underline" :aria-label="$t('arcadia.reader_nav.my_guides')">
                <ArcadiaMark class="text-lg" />
            </Link>

            <div class="flex items-center gap-1">
                <Link v-for="link in links" :key="link.key" :href="link.href"
                      :aria-current="current(link.href) ? 'page' : undefined"
                      class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium no-underline transition-colors"
                      :class="current(link.href) ? 'bg-hover text-ink' : 'text-ink-3 hover:bg-hover hover:text-ink'">
                    <component :is="link.icon" :size="15" aria-hidden="true" />
                    {{ $t(`arcadia.reader_nav.${link.key}`) }}
                </Link>
                <Button type="button" variant="ghost" size="icon-sm" :aria-label="$t('arcadia.my_guides.sign_out')" :title="$t('arcadia.my_guides.sign_out')" @click="signOut">
                    <LogOut :size="15" aria-hidden="true" />
                </Button>
            </div>
        </nav>
    </header>

    <!-- `overflow-x-clip`, as in `PublicLayout`: it crops sideways bleed without becoming a
         scroll container, so the guide's sticky notes panel still sticks to the screen. -->
    <main class="relative overflow-x-clip" role="main">
        <slot></slot>
    </main>
</template>
