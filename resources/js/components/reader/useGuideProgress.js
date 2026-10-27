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
 *
 * A line with a quick check is ticked by answering it (`check`): the server checks a reader's
 * answers at `progress.checkUrl` and ticks the line when all are right. The owner and the preview
 * are sent the answers with the page, so it checks them here, for the visit.
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

    // { passed, results: [{ correct, explanation? }] } for one answer per question.
    const check = async (key, answers, questions) => {
        let outcome

        if (progress?.checkUrl) {
            outcome = (await window.axios.put(progress.checkUrl, { key, answers })).data
        } else {
            const results = questions.map((question, index) => answers[index] === question.correct
                ? { correct: true }
                : { correct: false, explanation: question.explanation })
            outcome = { passed: results.every(result => result.correct), results }
        }

        if (outcome.passed) {
            mark(key, true)
        }

        return outcome
    }

    const isDone = key => done.value.has(key)
    const count = keys => keys.filter(key => done.value.has(key)).length

    return { isDone, toggle, count, check }
}
