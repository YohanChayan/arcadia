<script setup>
/**
 * The signed-in sidebar — brand, navigation, profile menu — shared by every dashboard layout.
 *
 * Each layout passes its own `nav`; an empty `nav` renders no navigation at all.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { PanelLeftClose, PanelLeft, MoreVertical, LogOut, UserPen } from '@lucide/vue'
import ArcadiaMark from '@/components/ArcadiaMark.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    // Items as in `lib/admin-nav.js`: key, route, match, exact, icon, badge.
    nav: { type: Array, required: true },
    // Translation key of the navigation's accessible name, and the prefix its item labels sit under.
    navLabel: { type: String, required: true },
    itemLabels: { type: String, required: true },
    // Where the brand leads.
    homeHref: { type: String, default: '/' },
})

const page = usePage()

const open = ref(true)
const profileOpen = ref(false)

// Start open on a desktop, closed on a phone — and decide it before first paint rather than
// in onMounted, which is why the old sidebar flashed collapsed on every desktop load.
const syncToViewport = () => { open.value = window.innerWidth >= 1024 }
if (typeof window !== 'undefined') syncToViewport()

onMounted(() => window.addEventListener('resize', syncToViewport))
onUnmounted(() => window.removeEventListener('resize', syncToViewport))

const user = computed(() => page.props.user)
const userName = computed(() => user.value?.name || '')
const userRole = computed(() => user.value?.role?.name || '')
const initial = computed(() => userName.value.charAt(0).toUpperCase())

// The active item is derived from the URL, not tracked by hand. Nothing highlighted at all
// before this — every link looked identical wherever you were.
const isActive = item => item.exact
    ? page.url.split('?')[0] === item.match
    : [item.match].flat().some(prefix => page.url.startsWith(prefix))

const logout = () => router.post(route('logout'))
</script>

<template>
    <!-- The rail is `fixed`; this spacer is what makes room for it beside the content. Below
         `lg` it reserves nothing, so an open rail overlays the page instead of squeezing the
         content into what is left of a phone's width. -->
    <aside :class="open ? 'w-0 lg:w-72' : 'w-0'" class="relative shrink-0 transition-all duration-300 ease-in-out">
        <div v-if="open" class="admin-rail flex flex-col justify-between gap-6 p-4 bg-ground/95 border-r border-rule backdrop-blur-xl">
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-5 border-b border-rule">
                    <a :href="homeHref" class="block px-3 py-2 rounded-control transition-colors hover:bg-ink/5">
                        <slot name="brand"><ArcadiaMark class="text-xl" /></slot>
                    </a>
                    <Button type="button" variant="ghost" size="icon" class="text-ink-3 hover:text-accent" :aria-label="$t('shell.collapse')" @click="open = false">
                        <PanelLeftClose :size="20" />
                    </Button>
                </div>

                <!-- Whatever a layout needs said above its navigation — Arcadia's editor: which
                     language's editor this is, and the way to the other. -->
                <slot name="context" />

                <nav v-if="props.nav.length" class="space-y-1" :aria-label="$t(navLabel)">
                    <Link v-for="item in props.nav" :key="item.key" :href="route(item.route)" :aria-current="isActive(item) ? 'page' : undefined" class="group relative flex items-center gap-3 px-3 py-2.5 rounded-control no-underline text-sm font-medium transition-colors" :class="isActive(item) ? 'bg-accent/10 text-accent' : 'text-ink-3 hover:bg-ink/5 hover:text-ink'">
                        <span v-if="isActive(item)" class="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-0.5 rounded-full bg-accent" aria-hidden="true"></span>
                        <component :is="item.icon" :size="20" />
                        <span class="flex-1">{{ $t(`${itemLabels}.${item.key}`) }}</span>
                        <span v-if="item.badge" class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-signal/15 text-signal">
                            {{ $t(`shell.badges.${item.badge}`) }}
                        </span>
                    </Link>
                </nav>
            </div>

            <div class="relative pt-5 border-t border-rule">
                <Button type="button" variant="ghost" class="h-auto w-full justify-start gap-3 px-3 py-2.5 text-left" :aria-expanded="profileOpen" @click="profileOpen = !profileOpen">
                    <span class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-on-accent bg-brand">{{ initial }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-ink truncate">{{ userName }}</span>
                        <span class="block text-xs text-ink-4 truncate">{{ userRole }}</span>
                    </span>
                    <MoreVertical :size="18" class="text-ink-4" />
                </Button>

                <Transition name="admin-menu">
                    <div v-if="profileOpen" class="absolute bottom-full left-0 right-0 mb-2 py-1 rounded-panel overflow-hidden bg-ground border border-rule shadow-[var(--shadow-lg)]">
                        <!-- The account's own settings: the profile, the password and passkeys. -->
                        <Link :href="route('profile.edit')" class="flex items-center gap-3 px-4 py-2.5 text-sm text-ink-2 no-underline transition-colors hover:bg-ink/5 hover:text-ink">
                            <UserPen :size="18" />
                            <span>{{ $t('shell.profile') }}</span>
                        </Link>

                        <Button type="button" variant="ghost-danger" class="h-auto w-full justify-start gap-3 rounded-none border-t border-rule px-4 py-2.5 font-normal text-ink-2" @click="logout">
                            <LogOut :size="18" />
                            <span>{{ $t('shell.logout') }}</span>
                        </Button>
                    </div>
                </Transition>
            </div>
        </div>

        <Button v-else type="button" variant="outline" size="icon" class="fixed top-4 left-4 z-(--layer-chrome) bg-ground/90 text-accent shadow-[var(--shadow-md)] backdrop-blur" :aria-label="$t('shell.expand')" @click="open = true">
            <PanelLeft :size="18" />
        </Button>
    </aside>
</template>

<style scoped>
.admin-rail {
    position: fixed;
    inset: 0 auto 0 0;
    width: 18rem;
    z-index: var(--layer-chrome);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--color-ink-4) transparent;
}

@media (max-width: 1024px) {
    .admin-rail { width: min(18rem, 85vw); }
}

.admin-menu-enter-active,
.admin-menu-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.admin-menu-enter-from,
.admin-menu-leave-to { opacity: 0; transform: translateY(4px); }

@media (prefers-reduced-motion: reduce) {
    .admin-menu-enter-active,
    .admin-menu-leave-active { transition: none; }
}
</style>
