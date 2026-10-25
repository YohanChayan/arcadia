<?php

namespace App\Http\Requests;

use App\Enums\CardKind;
use App\Enums\ContentNode;
use App\Enums\ItemKind;
use App\Enums\SectionKind;
use App\Services\VideoClip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One node of a version's content tree, for add and edit alike — its own fields, which differ
 * by kind. A new card or item also names its parent; where it goes among its siblings is the
 * app's to decide (`AppendContent`), never the form's.
 */
class ContentNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('node') === ContentNode::ITEMS->value) {
            $this->merge(VideoClip::input($this->all(), $this->input('kind') === ItemKind::YOUTUBE_VIDEO->value));
        }
    }

    public function rules(): array
    {
        $node = ContentNode::from($this->route('node'));
        $creating = $this->isMethod('post');
        $links = array_map(fn (ItemKind $kind) => $kind->value, array_filter(ItemKind::cases(), fn (ItemKind $kind) => $kind->isLink()));

        $parent = $creating && $node->parent() ? ['parent_id' => ['required', 'integer']] : [];

        return $parent + match ($node) {
            ContentNode::SECTIONS => [
                'kind' => ['required', Rule::enum(SectionKind::class)],
                'title' => ['nullable', 'string', 'max:160'],
                'body' => ['required', 'string', 'max:5000'],
            ],
            ContentNode::STAGES => [
                'title' => ['required', 'string', 'max:160'],
                'goal' => ['nullable', 'string', 'max:255'],
            ],
            ContentNode::CARDS => [
                'kind' => ['required', Rule::enum(CardKind::class)],
                'byline' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'quote' => ['nullable', 'string', 'max:1000'],
                'closing_note' => ['nullable', 'string', 'max:2000'],
            ],
            ContentNode::ITEMS => [
                'kind' => ['required', Rule::enum(ItemKind::class)],
                'title' => ['required', 'string', 'max:255'],
                // A video, a playlist or a site is a link; a note or a practice is the guide's own words.
                'url' => ['nullable', Rule::requiredIf(in_array($this->input('kind'), $links, true)), 'url:http,https', 'max:2048'],
                'hint' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:5000'],
                'is_preview' => ['boolean'],
                'is_available' => ['boolean'],
                ...VideoClip::rules(),
            ],
        };
    }

    public function messages(): array
    {
        return [
            'title.required' => __('arcadia.editor.content.errors.title'),
            'body.required' => __('arcadia.editor.content.errors.body'),
            'url.required' => __('arcadia.editor.content.errors.url'),
            'url.url' => __('arcadia.editor.content.errors.url'),
            ...VideoClip::messages(),
        ];
    }
}
