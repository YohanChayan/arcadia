import axios from 'axios'

window.axios = axios

import { createSSRApp, h } from 'vue'
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3'
import { ZiggyVue } from 'ziggy-js'
import { i18nVue } from 'laravel-vue-i18n'
import { layoutFor } from '@/lib/layout-for.js'

// A tab opened before a deploy can ask for a page chunk the deploy has replaced. Vite reports
// the failed import here; one full reload fetches the new build instead of leaving a blank page.
// The flag stops a chunk that is really missing from reloading forever; without storage
// (private mode) the error is left to Inertia instead.
window.addEventListener('vite:preloadError', (event) => {
    try {
        if (sessionStorage.getItem('chunk-reload')) return
        sessionStorage.setItem('chunk-reload', '1')
    } catch {
        return
    }
    event.preventDefault()
    location.reload()
})
router.on('navigate', () => {
    try { sessionStorage.removeItem('chunk-reload') } catch { /* nothing was set */ }
})

createInertiaApp({
    progress: {
        delay: 0,
        color: '#2EE6B8',
        includeCSS: true,
        showSpinner: false,
    },

    // Public pages are bundled with the app, so a first visit mounts without a second request.
    // The signed-in surfaces load on demand: no public visitor ever needs them. `errors/Error`
    // is in the eager half, so it renders even when a chunk fails to load.
    resolve: async name => {
        const pages = import.meta.glob(['./pages/**/*.vue', '!./pages/{settings,editor,reader}/**'], { eager: true })
        const privatePages = import.meta.glob('./pages/{settings,editor,reader}/**/*.vue')
        const path = `./pages/${name}.vue`
        const page = pages[path] ?? await privatePages[path]?.()

        // Name the missing page rather than fail on "reading 'default'" of undefined.
        if (!page) {
            throw new Error(`Inertia page not found: resources/js/pages/${name}.vue`)
        }

        page.default.layout = layoutFor(name, page)

        return page
    },
    setup({ el, App, props, plugin }) {
        // `createSSRApp`, so a guest's server-rendered page is hydrated rather than redrawn.
        const app = createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Link', Link)
            .component('Head', Head)
            .directive('focus', {
                mounted: (el, shouldFocus) => {
                    if (shouldFocus.value !== false) el.focus()
                },
            })

        // The messages arrive asynchronously, so mounting at once would render every `$t()` as
        // its raw key for a frame — and reka-ui's SelectValue copies the selected item's text
        // from the DOM, which Vue cannot track, so a closed select kept showing the key until
        // it was opened. Mount once the first language is in; later loads only swap messages.
        let mounted = false
        app.use(i18nVue, {
            onLoad: () => {
                if (mounted) return
                mounted = true
                app.mount(el)
            },
            // The plugin asks for `${lang}.json` and `php_${lang}.json`; only the php_ files
            // exist. Resolve a missing one to nothing rather than reject.
            resolve: async lang => {
                const langs = import.meta.glob('../../lang/*.json')
                return langs[`../../lang/${lang}.json`]?.() ?? {}
            },
        })
    },
})
