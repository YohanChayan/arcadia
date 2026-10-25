import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import { trans } from 'laravel-vue-i18n'

/**
 * A reader's own notes on a guide, on a line (`item`) or a stage (`stage`), by that node's key.
 * They arrive with the page (`notes.list`) and are written one at a time to `notes.storeUrl`
 * (and `storeUrl/{id}` to change or delete one), kept in place here — nothing reloads.
 *
 * `open(target)` is how any line or stage asks to show its notes; who shows them — the side panel
 * on a wide screen, a sheet on a phone — watches `opened`.
 *
 * Without `notes` — the owner, the editor's preview — there is nothing: `enabled` is false.
 */
export const useGuideNotes = notes => {
    const list = ref(notes?.list ?? [])
    const opened = ref(null)
    // Bumped on every `open`, so asking twice for the same target still moves the panel to it.
    const openedAt = ref(0)

    const enabled = Boolean(notes?.storeUrl)
    const maxLength = notes?.maxLength ?? 2000

    const idOf = target => `${target.kind}:${target.key}`

    const forTarget = target => list.value.filter(note => note.target_kind === target.kind && note.target_key === target.key)
    const countFor = target => forTarget(target).length

    const failed = () => toast.error(trans('arcadia.reader.notes.failed'))

    const add = async (target, body) => {
        try {
            const { data } = await window.axios.post(notes.storeUrl, { target_kind: target.kind, target_key: target.key, body })
            list.value = [...list.value, data]
            return true
        } catch {
            failed()
            return false
        }
    }

    const update = async (note, body) => {
        try {
            const { data } = await window.axios.put(`${notes.storeUrl}/${note.id}`, { body })
            list.value = list.value.map(each => (each.id === note.id ? data : each))
            return true
        } catch {
            failed()
            return false
        }
    }

    const remove = async note => {
        const before = list.value
        list.value = list.value.filter(each => each.id !== note.id)

        try {
            await window.axios.delete(`${notes.storeUrl}/${note.id}`)
        } catch {
            list.value = before
            failed()
        }
    }

    const open = target => {
        opened.value = target
        openedAt.value = Date.now()
    }

    const close = () => {
        opened.value = null
    }

    return {
        enabled,
        maxLength,
        total: computed(() => list.value.length),
        idOf,
        forTarget,
        countFor,
        add,
        update,
        remove,
        opened,
        openedAt,
        open,
        close,
    }
}
