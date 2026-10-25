import { usePage } from '@inertiajs/vue3'

/**
 * A guides editor route by its short name — `editorRoute('guides.edit', id)`.
 *
 * Arcadia registers the editor once per language (`es.editor.*` at `/es/editor`,
 * `en.editor.*` at `/en/editor`), and the shared `locale` is the page's own language, so
 * it names the right set. Without this every editor screen would spell the prefix out, and one
 * that spelled it wrong would link to the other language's editor.
 */
export const useEditorRoute = () => {
    const page = usePage()

    return (name, params) => route(`${page.props.locale}.editor.${name}`, params)
}
