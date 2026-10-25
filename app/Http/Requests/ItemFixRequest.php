<?php

namespace App\Http\Requests;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Services\VideoClip;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A repair of one line, in every version that carries it: only what `FixItemEverywhere` may
 * change. Never its key, kind or place — that is a new version's job.
 */
class ItemFixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kind = Item::query()->where('key', $this->route('key'))->value('kind');

        $this->merge(VideoClip::input($this->all(), $kind === ItemKind::YOUTUBE_VIDEO));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'hint' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'is_available' => ['boolean'],
            ...VideoClip::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('arcadia.editor.content.errors.title'),
            'url.url' => __('arcadia.editor.content.errors.url'),
            ...VideoClip::messages(),
        ];
    }
}
