/**
 * YouTube's IFrame Player API, loaded once and only when asked — after the reader presses play,
 * which is their consent to anything from YouTube reaching the page (`VideoEmbed`).
 *
 * The script calls a global `onYouTubeIframeAPIReady` when it is ready; any earlier one is kept.
 */
let loading = null

export const loadYoutubeApi = () => {
    if (window.YT?.Player) {
        return Promise.resolve(window.YT)
    }

    loading ??= new Promise((resolve, reject) => {
        const previous = window.onYouTubeIframeAPIReady
        window.onYouTubeIframeAPIReady = () => {
            previous?.()
            resolve(window.YT)
        }

        const script = document.createElement('script')
        script.src = 'https://www.youtube.com/iframe_api'
        script.async = true
        script.onerror = () => {
            loading = null
            reject(new Error('The YouTube player API did not load.'))
        }
        document.head.append(script)
    })

    return loading
}
