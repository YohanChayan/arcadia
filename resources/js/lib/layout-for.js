import ShellLayout from '@/layouts/ShellLayout.vue'
import ErrorLayout from '@/layouts/ErrorLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'

/**
 * The layout a page gets, decided here and never by the page — except a page that declares one.
 * The error page has its own; the password confirmation keeps the kit's card; the account settings
 * sit inside the shell their owner works in, with the settings' own navigation; every other page
 * gets `ShellLayout`, which picks the public, reader or editor shell.
 *
 * Shared by `app.js` and `ssr.js`: the two must agree, or hydration would.
 */
export const layoutFor = (name, page) => {
    if (name === 'errors/Error') return ErrorLayout
    if (name.startsWith('settings/')) return [ShellLayout, SettingsLayout]
    if (name === 'auth/ConfirmPassword') return AuthLayout

    return page.default?.layout || ShellLayout
}
