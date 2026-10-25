import { trans } from 'laravel-vue-i18n'

/** Seconds as a video's clock: 840 → "14:00", 3769 → "1:02:49". */
export const clock = (seconds) => {
    const [h, m, s] = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60]
    const pad = (value) => String(value).padStart(2, '0')

    return h ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`
}

/** Which part of a video a line plays — `{ start, end }` in seconds — said in words, or null. */
export const clipLabel = (start, end) => {
    if (start == null) {
        return null
    }

    return end == null
        ? trans('arcadia.reader.clip_from', { start: clock(start) })
        : trans('arcadia.reader.clip', { start: clock(start), end: clock(end), minutes: Math.max(1, Math.round((end - start) / 60)) })
}
