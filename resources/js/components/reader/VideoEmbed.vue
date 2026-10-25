<script setup>
/**
 * A YouTube video or playlist that loads only when the reader asks for it.
 *
 * Even `youtube-nocookie.com` writes to the browser's storage as soon as its iframe loads, before
 * any click (doubt 12), so nothing from YouTube is on the page — not even a thumbnail — until the
 * reader presses play. That click is their consent. The player itself is never modified: YouTube's
 * policies forbid it (finding 39).
 *
 * A clip with an end plays through YouTube's IFrame Player API instead, so the page knows where the
 * video is: a countdown under the player, and at the end a pause and a choice — keep watching past
 * the clip, or go to the next line. All of it **below** the player, never over it: YouTube forbids
 * any visual element in front of an embedded player. Should the API not load, the clip falls back
 * to the plain player with its `start` and `end`.
 *
 * The one reader component on Arcadia's own tokens, not the `--guide-*` ones: a reader's colours
 * cover the page's layout and content, never the video (2026-10-14), so its frame stays Arcadia's.
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { Play } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { clock } from '@/lib/clock.js'
import { loadYoutubeApi } from './youtubeApi.js'

const props = defineProps({
    // { video: id, start?, end? } or { playlist: id } — start and end in seconds from the video's start
    embed: { type: Object, required: true },
    title: { type: String, required: true },
})

const emit = defineEmits(['next'])

const loaded = ref(false)

// A clip is YouTube's own `start` and `end` parameters: the player plays that part, unmodified.
const clip = computed(() => ['start', 'end'].filter((name) => props.embed[name] != null).map((name) => `&${name}=${props.embed[name]}`).join(''))

const src = computed(() => props.embed.video
    ? `https://www.youtube-nocookie.com/embed/${props.embed.video}?rel=0&autoplay=1${clip.value}`
    : `https://www.youtube-nocookie.com/embed/videoseries?list=${props.embed.playlist}&rel=0&autoplay=1`)

// A clip that ends is played through the API, to count down and stop there from this page.
const counted = computed(() => Boolean(props.embed.video) && props.embed.end != null)
const apiFailed = ref(false)

const host = ref(null)
let player = null
let timer = null

const now = ref(null)
const atEnd = ref(false)
// Past the clip on purpose: it no longer stops.
const continued = ref(false)

const start = computed(() => props.embed.start ?? 0)
const remaining = computed(() => Math.max(0, Math.ceil(props.embed.end - (now.value ?? start.value))))
const progress = computed(() => Math.min(1, Math.max(0, ((now.value ?? start.value) - start.value) / (props.embed.end - start.value))))

const stopTimer = () => {
    clearInterval(timer)
    timer = null
}

const tick = () => {
    const time = player?.getCurrentTime?.()
    if (typeof time !== 'number') {
        return
    }

    now.value = time
    atEnd.value = !continued.value && time >= props.embed.end

    if (atEnd.value) {
        player.pauseVideo()
        stopTimer()
    }
}

const play = async () => {
    loaded.value = true

    if (!counted.value) {
        return
    }

    await nextTick()

    try {
        const YT = await loadYoutubeApi()

        player = new YT.Player(host.value, {
            host: 'https://www.youtube-nocookie.com',
            videoId: props.embed.video,
            width: '100%',
            height: '100%',
            playerVars: { autoplay: 1, rel: 0, start: start.value, origin: window.location.origin },
            events: {
                // Its iframe is named for the line, as the plain one is.
                onReady: ({ target }) => target.getIframe().setAttribute('title', props.title),
                onStateChange: ({ data }) => {
                    if (data === YT.PlayerState.PLAYING) {
                        stopTimer()
                        timer = setInterval(tick, 250)
                    } else {
                        stopTimer()
                        tick()
                    }
                },
            },
        })
    } catch {
        apiFailed.value = true
    }
}

const keepWatching = () => {
    continued.value = true
    atEnd.value = false
    player?.playVideo()
}

onBeforeUnmount(() => {
    stopTimer()
    player?.destroy?.()
})
</script>

<template>
    <div class="mt-3">
        <div class="relative aspect-video w-full overflow-hidden rounded-xl bg-raised-solid">
            <template v-if="loaded">
                <!-- The API replaces the inner element with its iframe; Vue only ever removes the outer one. -->
                <div v-if="counted && !apiFailed" class="absolute inset-0"><div ref="host" /></div>
                <iframe v-else :src="src" :title="title" class="absolute inset-0 h-full w-full border-0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </template>

            <button v-else type="button" class="absolute inset-0 flex w-full flex-col items-center justify-center gap-3 p-4 text-center"
                    @click="play">
                <span class="flex size-14 items-center justify-center rounded-full bg-accent text-on-accent shadow-lg">
                    <Play :size="26" aria-hidden="true" class="ml-0.5" />
                </span>
                <span class="text-sm font-medium text-ink">{{ embed.video ? $t('arcadia.reader.play') : $t('arcadia.reader.play_list') }}</span>
                <span class="max-w-xs text-xs text-ink-3">{{ $t('arcadia.reader.play_note') }}</span>
            </button>
        </div>

        <template v-if="loaded && counted && !apiFailed">
            <div class="mt-2 flex items-center gap-3">
                <div class="h-1 flex-1 overflow-hidden rounded-full bg-well" aria-hidden="true">
                    <div class="h-full rounded-full bg-accent transition-[width] duration-200" :style="{ width: `${progress * 100}%` }"></div>
                </div>
                <span class="font-mono text-xs tabular-nums" :class="!continued && remaining <= 10 ? 'font-semibold text-accent' : 'text-ink-3'" aria-live="off">
                    {{ continued ? $t('arcadia.reader.clip_past') : atEnd ? $t('arcadia.reader.clip_over') : $t('arcadia.reader.clip_ends_in', { time: clock(remaining) }) }}
                </span>
            </div>

            <div v-if="atEnd" class="mt-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rule bg-raised-solid px-4 py-3" role="status">
                <p class="m-0 text-sm text-ink">{{ $t('arcadia.reader.clip_done') }}</p>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="keepWatching">{{ $t('arcadia.reader.keep_watching') }}</Button>
                    <Button type="button" variant="brand" size="sm" @click="emit('next')">{{ $t('arcadia.reader.next') }}</Button>
                </div>
            </div>
        </template>
    </div>
</template>
