/**
 * Which shell an Arcadia page renders in — `ShellLayout`'s one decision, kept here so it reads
 * as a rule.
 *
 * `editor/*` is the owner's editor. A signed-in reader's pages — "My guides", help, and
 * a guide they read — get the reader shell, a bar to move between them. Not the device limit,
 * which a reader reaches before they may go anywhere, and not a guide the owner reads or
 * previews. The account settings sit in the shell their owner works in: the editor's for the
 * owner, the reader's for a reader. Everything else is the public shell.
 */
const isReaderPage = (component, user) => (component.startsWith('reader/') && component !== 'reader/DeviceLimit')
    || (component === 'Guide' && user?.role?.alias === 'reader')

export const shellFor = (component, user) => {
    if (component?.startsWith('editor/')) return 'editor'
    if (component?.startsWith('settings/')) return user?.role?.alias === 'admin' ? 'editor' : 'reader'

    return isReaderPage(component ?? '', user) ? 'reader' : 'public'
}
