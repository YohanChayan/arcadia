<?php

namespace App\Http\Requests;

use App\Enums\ImageSlot;
use App\Models\Guide;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * An image for one of a guide's slots, held to that slot's rule (`ImageSlot`): a JPEG, PNG or
 * WebP, large enough for every crop without upscaling, within the slot's ratio band — and, for a
 * slot of many, one the slot still has room for.
 */
class GuideImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $slot = $this->slot();

        return [
            'image' => [
                'bail',
                'required',
                function (string $attribute, mixed $value, Closure $fail) use ($slot) {
                    if ($slot->holdsMany() && $this->guide()->images()->where('slot', $slot)->count() >= $slot->maxImages()) {
                        $fail(__('arcadia.editor.images.errors.full', ['max' => $slot->maxImages()]));
                    }
                },
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.ImageSlot::MAX_KILOBYTES,
                "dimensions:min_width={$slot->minWidth()},min_height={$slot->minHeight()}",
                function (string $attribute, mixed $value, Closure $fail) use ($slot) {
                    [$min, $max] = $slot->ratioRange();
                    [$width, $height] = $value instanceof UploadedFile ? (@getimagesize($value->getRealPath()) ?: [0, 0]) : [0, 0];
                    $ratio = $height > 0 ? $width / $height : 0;

                    if ($ratio < $min || $ratio > $max) {
                        $fail(__("arcadia.editor.images.slots.{$slot->value}.ratio", ['ratio' => number_format($ratio, 2)]));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        $slot = $this->slot();

        return [
            'image.required' => __('arcadia.editor.images.errors.required'),
            'image.file' => __('arcadia.editor.images.errors.required'),
            'image.mimes' => __('arcadia.editor.images.errors.type'),
            'image.max' => __('arcadia.editor.images.errors.size', ['max' => intdiv(ImageSlot::MAX_KILOBYTES, 1024)]),
            'image.dimensions' => __('arcadia.editor.images.errors.dimensions', ['width' => $slot->minWidth(), 'height' => $slot->minHeight()]),
        ];
    }

    private function guide(): Guide
    {
        return $this->route('guide');
    }

    private function slot(): ImageSlot
    {
        $slot = $this->route('slot');

        return $slot instanceof ImageSlot ? $slot : ImageSlot::from($slot);
    }
}
