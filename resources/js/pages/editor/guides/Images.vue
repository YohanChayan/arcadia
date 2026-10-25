<script setup>
/**
 * How a guide is presented, one section under another: its pitch — the lines its link's page
 * shows someone who is not yet a reader (ADR 0022) — then its images by slot (`ImageSlot`): the
 * cover, and below it the flyers, social media images that sell it, as many as the slot holds. The cover and flyer prompts open from
 * the header in a popup, each filled from the guide, to copy into ChatGPT or Gemini
 * (`PromptTemplate::COVER`, `PromptTemplate::GUIDE_FLYER`), in whichever of its versions the owner
 * picks — the latest unless they choose another (`VersionedPromptFiller`). The owner uploads the
 * image as generated; the server holds it to the slot's rule and cuts the crops each surface shows,
 * which appear there as they will be seen — the cover's three, each flyer whole. Uploading to the
 * cover replaces it; uploading flyers — several chosen at once, or dropped on their section — adds
 * them one request at a time, as the server's post limit is two images. Removing deletes an image
 * and all its crops.
 *
 * What the link's page brought in is on the guide's card in the list, not here.
 */
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Eye, ExternalLink, ImageUp, Megaphone, Sparkles, Trash2 } from '@lucide/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import VersionedPromptFiller from '@/components/editor/prompts/VersionedPromptFiller.vue'
import EditorTitle from '@/components/editor/EditorTitle.vue'
import EditorBreadcrumb from '@/components/editor/EditorBreadcrumb.vue'
import FlyerGallery from '@/components/editor/images/FlyerGallery.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Eyebrow from '@/components/common/Eyebrow.vue'
import { Button, buttonVariants } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Textarea } from '@/components/ui/textarea'
import { Spinner } from '@/components/ui/spinner'
import { useEditorRoute } from '@/composables/useEditorRoute.js'

const props = defineProps({
    // { id, title, pitch }
    guide: { type: Object, required: true },
    pitchMax: { type: Number, default: 600 },
    // The cover prompt in every version, oldest first — { id, name, versions } — or null where it
    // is not seeded; the guide's values for its blanks; and the choices of its level and category.
    coverPrompt: { type: Object, default: null },
    coverPromptValues: { type: Object, default: () => ({}) },
    // The guide flyer prompt the same way, and the guide's values for it.
    flyerPrompt: { type: Object, default: null },
    flyerPromptValues: { type: Object, default: () => ({}) },
    choices: { type: Object, default: () => ({}) },
    // [{ slot, label, min_width, min_height, ratio: [min, max], max_mb, max_images,
    //    images: [{ id, original, crops, width, height, bytes }] }], images in the order added
    slots: { type: Array, required: true },
})

const editorRoute = useEditorRoute()

// Which prompt's popup is open.
const prompting = ref(false)
const flyering = ref(false)

const pitchForm = useForm({ pitch: props.guide.pitch ?? '' })

// Saved, the saved text is the new baseline, so the button waits for the next change.
const savePitch = () => pitchForm.put(editorRoute('presentation.update', { guide: props.guide.id }), {
    preserveScroll: true,
    onSuccess: () => pitchForm.defaults(),
})

// One request per file, one slot at a time: `{ slot, done, total }` while a batch is going up.
const queue = ref(null)
// What the last batch refused, `{ slot, list: [{ name, message }] }` — shown under that slot only.
const failures = ref({ slot: null, list: [] })
const inputs = ref({})
const dropping = ref(null)

const isFull = slot => slot.max_images > 1 && slot.images.length >= slot.max_images

const send = (slot, file) => new Promise(resolve => {
    router.post(editorRoute('images.store', { guide: props.guide.id, slot: slot.slot }), { image: file }, {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onError: errors => failures.value.list.push({ name: file.name, message: errors.image }),
        onFinish: resolve,
    })
})

const upload = async (slot, files) => {
    // A slot of one takes the first file only.
    const batch = [...(files ?? [])].slice(0, slot.max_images > 1 ? undefined : 1)

    if (!batch.length || queue.value) {
        return
    }

    failures.value = { slot: slot.slot, list: [] }
    queue.value = { slot: slot.slot, done: 0, total: batch.length }

    for (const file of batch) {
        await send(slot, file)
        queue.value.done++
    }

    queue.value = null
}

const choose = (slot, event) => {
    const files = [...(event.target.files ?? [])]
    // Cleared, so choosing the same file again after an error still fires `change`.
    event.target.value = ''
    upload(slot, files)
}

const drop = (slot, event) => {
    dropping.value = null
    upload(slot, [...(event.dataTransfer?.files ?? [])].filter(file => file.type.startsWith('image/')))
}

// The image a remove waits to be confirmed for.
const pendingRemove = ref(null)
const removing = ref(false)

const confirmRemove = () => {
    removing.value = true
    router.delete(editorRoute('images.destroy', { guide: props.guide.id, image: pendingRemove.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            removing.value = false
            pendingRemove.value = null
        },
    })
}

const formatSize = bytes => bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.round(bytes / 1024)} KB`
</script>

<template>
    <EditorTitle :parts="[guide.title, $t('arcadia.editor.images.title')]" />

    <div class="py-6">
        <EditorBreadcrumb :trail="[{ label: $t('arcadia.editor.guides.title'), route: 'guides.index' }, { label: guide.title, route: 'content.show', params: { guide: guide.id } }, { label: $t('arcadia.editor.images.title') }]" />

        <PageHeader :title="$t('arcadia.editor.images.title')" :description="guide.title">
            <template #actions>
                <Button type="button" variant="outline" @click="prompting = true">
                    <Sparkles :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.images.prompt.title') }}
                </Button>
                <Button type="button" variant="outline" @click="flyering = true">
                    <Megaphone :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.images.flyer.open') }}
                </Button>
                <a :href="editorRoute('presentation.preview', { guide: guide.id })" target="_blank" rel="noopener"
                   :class="buttonVariants({ variant: 'outline' })" class="no-underline">
                    <Eye :size="16" aria-hidden="true" />
                    {{ $t('arcadia.editor.images.preview') }}
                </a>
            </template>
        </PageHeader>

        <p class="mt-0 mb-6 max-w-2xl text-sm text-ink-3">{{ $t('arcadia.editor.images.lead') }}</p>

        <form class="panel mb-6 p-6" @submit.prevent="savePitch">
            <Field :data-invalid="pitchForm.errors.pitch ? true : undefined">
                <FieldLabel for="pitch"><Eyebrow as="span">{{ $t('arcadia.editor.images.pitch.label') }}</Eyebrow></FieldLabel>
                <Textarea id="pitch" v-model="pitchForm.pitch" rows="4" :maxlength="pitchMax" />
                <FieldError v-if="pitchForm.errors.pitch">{{ pitchForm.errors.pitch }}</FieldError>
                <FieldDescription v-else>{{ $t('arcadia.editor.images.pitch.hint') }}</FieldDescription>
            </Field>
            <Button type="submit" variant="outline" class="mt-4" :disabled="pitchForm.processing || !pitchForm.isDirty">
                {{ $t('arcadia.editor.images.pitch.save') }}
            </Button>
        </form>

        <!-- Each slot: its rule, the upload, and its images as each surface shows them. A slot of
             many also takes files dropped anywhere on it. -->
        <section v-for="slot in slots" :key="slot.slot" class="panel mb-6 p-6 transition-colors"
                 :class="dropping === slot.slot ? 'border-accent bg-accent/5' : ''"
                 :aria-labelledby="`slot-${slot.slot}-title`"
                 @dragover.prevent="slot.max_images > 1 && !isFull(slot) && (dropping = slot.slot)"
                 @dragleave.self="dropping = null"
                 @drop.prevent="slot.max_images > 1 && !isFull(slot) && drop(slot, $event)">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Eyebrow :id="`slot-${slot.slot}-title`" as="h2">
                        {{ slot.label }}
                        <span v-if="slot.max_images > 1" class="ml-1 font-normal text-ink-4">
                            · {{ $t('arcadia.editor.images.count', { count: slot.images.length, max: slot.max_images }) }}
                        </span>
                    </Eyebrow>
                    <p class="mt-1 mb-0 max-w-3xl text-sm text-ink-3">{{ $t(`arcadia.editor.images.slots.${slot.slot}.hint`) }}</p>
                    <p class="mt-1 mb-0 text-xs text-ink-4">
                        {{ $t(`arcadia.editor.images.slots.${slot.slot}.rule`, { max: slot.max_mb, width: slot.min_width, height: slot.min_height }) }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <Button type="button" variant="brand" :disabled="queue !== null || isFull(slot)" @click="inputs[slot.slot]?.click()">
                        <Spinner v-if="queue?.slot === slot.slot" class="size-4" />
                        <ImageUp v-else :size="16" aria-hidden="true" />
                        <template v-if="queue?.slot === slot.slot">
                            {{ queue.total > 1
                                ? $t('arcadia.editor.images.uploading_many', { done: queue.done + 1, total: queue.total })
                                : $t('arcadia.editor.images.uploading') }}
                        </template>
                        <template v-else-if="slot.max_images > 1">{{ $t('arcadia.editor.images.add') }}</template>
                        <template v-else>{{ $t(slot.images.length ? 'arcadia.editor.images.replace' : 'arcadia.editor.images.upload') }}</template>
                    </Button>
                    <Button v-if="slot.max_images === 1 && slot.images.length" type="button" variant="ghost-danger" size="icon"
                            :disabled="queue !== null"
                            :aria-label="$t('arcadia.editor.images.remove.action')" :title="$t('arcadia.editor.images.remove.action')"
                            @click="pendingRemove = slot.images[0]">
                        <Trash2 :size="16" aria-hidden="true" />
                    </Button>
                    <input :ref="element => { inputs[slot.slot] = element }" type="file" accept="image/jpeg,image/png,image/webp"
                           :multiple="slot.max_images > 1" class="hidden" @change="choose(slot, $event)">
                </div>
            </div>

            <ul v-if="failures.slot === slot.slot && failures.list.length" class="mt-3 mb-0 list-none space-y-1 p-0 text-sm text-danger" role="alert">
                <li v-for="(failure, index) in failures.list" :key="index">
                    <template v-if="slot.max_images > 1"><span class="font-medium">{{ failure.name }}</span> — </template>{{ failure.message }}
                </li>
            </ul>

            <!-- The flyers, each whole, in the order they were added. -->
            <template v-if="slot.max_images > 1">
                <FlyerGallery v-if="slot.images.length" class="mt-5" :images="slot.images" :disabled="queue !== null"
                              @remove="image => pendingRemove = image" />
                <p v-else class="mt-5 mb-0 rounded-control border border-dashed border-rule p-6 text-center text-sm text-ink-4">
                    {{ dropping === slot.slot ? $t('arcadia.editor.images.drop') : $t('arcadia.editor.images.empty') }}
                </p>
            </template>

            <!-- The cover: the wide crop beside the smaller ones on a wide screen, above them otherwise. -->
            <template v-else>
                <div v-for="image in slot.images.slice(0, 1)" :key="image.id"
                     class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <figure class="m-0">
                        <img :src="image.crops.hero" alt="" class="block w-full rounded-control border border-rule">
                        <figcaption class="mt-1.5 text-xs text-ink-4">{{ $t('arcadia.editor.images.crops.hero') }}</figcaption>
                    </figure>
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-wrap items-end gap-5">
                            <figure class="m-0 w-full max-w-sm">
                                <img :src="image.crops.share" alt="" class="block aspect-[1200/630] w-full rounded-control border border-rule object-cover">
                                <figcaption class="mt-1.5 text-xs text-ink-4">{{ $t('arcadia.editor.images.crops.share') }}</figcaption>
                            </figure>
                            <figure class="m-0">
                                <img :src="image.crops.thumb" alt="" class="block size-20 rounded-control border border-rule object-cover">
                                <figcaption class="mt-1.5 text-xs text-ink-4">{{ $t('arcadia.editor.images.crops.thumb') }}</figcaption>
                            </figure>
                        </div>
                        <p class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-4">
                            <span>{{ $t('arcadia.editor.images.original', { width: image.width, height: image.height, size: formatSize(image.bytes) }) }}</span>
                            <a :href="image.original" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-ink-3 no-underline hover:text-accent">
                                <ExternalLink :size="12" aria-hidden="true" />
                                {{ $t('arcadia.editor.images.download') }}
                            </a>
                        </p>
                    </div>
                </div>
                <p v-if="!slot.images.length" class="mt-5 mb-0 rounded-control border border-dashed border-rule p-6 text-center text-sm text-ink-4">
                    {{ $t('arcadia.editor.images.empty') }}
                </p>
            </template>
        </section>

        <!-- The cover prompt, filled from the guide: copy it into ChatGPT or Gemini. -->
        <Dialog v-model:open="prompting">
            <DialogContent class="w-[calc(100%-2rem)] max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('arcadia.editor.images.prompt.title') }}</DialogTitle>
                    <DialogDescription>{{ coverPrompt ? $t('arcadia.editor.images.prompt.hint') : $t('arcadia.editor.images.prompt.missing') }}</DialogDescription>
                </DialogHeader>
                <VersionedPromptFiller v-if="coverPrompt" :prompt="coverPrompt" :choices="choices" :initial="coverPromptValues" />
            </DialogContent>
        </Dialog>

        <!-- The flyer prompt, filled from the guide the same way. -->
        <Dialog v-model:open="flyering">
            <DialogContent class="w-[calc(100%-2rem)] max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('arcadia.editor.images.flyer.open') }}</DialogTitle>
                    <DialogDescription>{{ flyerPrompt ? $t('arcadia.editor.images.flyer.hint') : $t('arcadia.editor.images.flyer.missing') }}</DialogDescription>
                </DialogHeader>
                <VersionedPromptFiller v-if="flyerPrompt" :prompt="flyerPrompt" :choices="choices" :initial="flyerPromptValues" />
            </DialogContent>
        </Dialog>

        <ConfirmDialog :open="pendingRemove !== null"
                       :title="$t('arcadia.editor.images.remove.title')"
                       :body="$t('arcadia.editor.images.remove.body')"
                       :confirm-label="$t('arcadia.editor.images.remove.confirm')"
                       :processing="removing"
                       @confirm="confirmRemove"
                       @cancel="pendingRemove = null" />
    </div>
</template>
