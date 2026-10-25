import { createInertiaApp, Link, Head } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from '@vue/server-renderer'
import { createSSRApp, h } from 'vue'
import { route } from 'ziggy-js'
import { i18nVue } from 'laravel-vue-i18n'
import { layoutFor } from '@/lib/layout-for.js'

/**
 * The server render of a guest's page — what a link preview's crawler reads, since it runs no
 * JavaScript: the title, the description and the share image. Signed-in pages are never rendered
 * here (`DisableSsrForAuth`).
 */
createServer(page =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: name => {
            const pages = import.meta.glob('./pages/**/*.vue', { eager: true })
            const component = pages[`./pages/${name}.vue`]

            if (!component) {
                throw new Error(`Inertia page not found: resources/js/pages/${name}.vue`)
            }

            component.default.layout = layoutFor(name, component)

            return component
        },
        setup({ App, props, plugin }) {
            // Ziggy's routes, shared by `HandleInertiaRequests`: on the server there is no
            // `@routes` script to read them from.
            const shared = props?.initialPage?.props?.ziggy
            const Ziggy = shared ? { ...shared, location: new URL(shared.location) } : undefined

            globalThis.Ziggy = Ziggy
            globalThis.route = (name, params, absolute, config = Ziggy) => route(name, params, absolute, config)

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .mixin({
                    methods: {
                        route: (name, params, absolute, config = Ziggy) => route(name, params, absolute, config),
                    },
                })
                .use(i18nVue, {
                    lang: props?.initialPage?.props?.locale,
                    resolve: lang => {
                        const langs = import.meta.glob('../../lang/*.json', { eager: true })
                        return langs[`../../lang/${lang}.json`]?.default ?? {}
                    },
                })
                .component('Link', Link)
                .component('Head', Head)
                .directive('focus', {})
        },
    }),
)
