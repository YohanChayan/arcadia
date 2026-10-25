/**
 * Each content node's own fields, in the order the form shows them — the one list `NodeForm`
 * renders from, so adding a field is an entry here (and its rule in `ContentNodeRequest`).
 *
 * `options` names a list in the page's `kinds`; `when` hides a field that does not apply, given
 * the form's current values and the item kinds that are links. A `clock` field holds seconds and
 * is typed as a video's time, 14:00.
 */

const isVideo = (values) => values.kind === 'youtube_video'
export const FIELDS = {
    sections: [
        { name: 'kind', type: 'select', options: 'sections' },
        { name: 'title', type: 'text', optional: true },
        { name: 'body', type: 'textarea' },
    ],
    stages: [
        { name: 'title', type: 'text' },
        { name: 'goal', type: 'text', optional: true },
    ],
    cards: [
        { name: 'kind', type: 'select', options: 'cards' },
        { name: 'byline', type: 'text', optional: true },
        { name: 'description', type: 'textarea', optional: true },
        { name: 'quote', type: 'textarea', optional: true },
        { name: 'closing_note', type: 'textarea', optional: true },
    ],
    items: [
        { name: 'kind', type: 'select', options: 'items' },
        { name: 'title', type: 'text' },
        { name: 'url', type: 'url', when: (values, linkKinds) => linkKinds.includes(values.kind) },
        { name: 'hint', type: 'text', optional: true },
        { name: 'body', type: 'textarea', optional: true },
        // The part of the video to play, and where that comes from: no evidence, no clip.
        { name: 'clip_start', type: 'clock', optional: true, when: isVideo },
        { name: 'clip_end', type: 'clock', optional: true, when: isVideo },
        { name: 'clip_evidence', type: 'textarea', optional: true, when: isVideo },
        { name: 'is_preview', type: 'checkbox', default: false },
        { name: 'is_available', type: 'checkbox', default: true },
    ],
}
