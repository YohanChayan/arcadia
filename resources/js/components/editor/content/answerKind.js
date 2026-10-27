/**
 * What kind of AI answer a chosen file looks like, read in the browser before anything is sent:
 * `activities` when it names an activity `family`, `content` when it carries `stages`, else null.
 * A hint for the owner who picked the wrong import, never a decision — the server checks the file.
 */
export const sniffAnswerKind = async file => {
    try {
        const text = await file.text()

        if (/"family"\s*:/.test(text)) return 'activities'
        if (/"stages"\s*:/.test(text)) return 'content'
    } catch {}

    return null
}
