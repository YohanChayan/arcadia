import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { trans } from 'laravel-vue-i18n'

/**
 * A reader's checkmarks, on their account. The page arrives with the keys they ticked
 * (`progress.ticked`), so the server renders them and nothing flips after mount. Each tap is one
 * idempotent write to `progress.saveUrl`, shown at once and taken back, with a message, if it
 * fails. Keyed by item key, which every version shares, so a reader moved to a new version keeps
 * their progress on every line that survived.
 *
 * Without `progress` — the owner reading as-is, or the editor's preview — ticks last for the
 * visit and are written nowhere.
 */
export const useGuideProgress = progress => {
    const done = ref(new Set(progress?.ticked ?? []))

    const mark = (key, value) => {
        const next = new Set(done.value)
        value ? next.add(key) : next.delete(key)
        done.value = next
    }

    const toggle = key => {
        const value = !done.value.has(key)
        mark(key, value)

        if (!progress?.saveUrl) return

        window.axios.put(progress.saveUrl, { key, done: value }).catch(() => {
            mark(key, !value)
            toast.error(trans('arcadia.reader.progress_failed'))
        })
    }

    const isDone = key => done.value.has(key)
    const count = keys => keys.filter(key => done.value.has(key)).length

    return { isDone, toggle, count }
}
