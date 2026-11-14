<?php

namespace App\Http\Requests;

use App\Enums\GuideBulkAction;
use App\Enums\GuideStatus;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which guides a bulk action on the guides list reaches: the ones ticked by id, or every guide
 * the list's filter matches — across all its pages — less any unticked after.
 *
 * Always this editor's language's guides only: an id of the other language matches nothing.
 */
class BulkGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(GuideBulkAction::class)],
            'all' => ['boolean'],
            'ids' => ['required_unless:all,true', 'array'],
            'ids.*' => ['integer'],
            // The list's filter, read only when `all` is set.
            'status' => ['nullable', Rule::enum(GuideStatus::class)],
            'search' => ['nullable', 'string', 'max:160'],
            'except' => ['array'],
            'except.*' => ['integer'],
        ];
    }

    public function action(): GuideBulkAction
    {
        return GuideBulkAction::from($this->validated('action'));
    }

    /** @return Builder<Guide> */
    public function guides(): Builder
    {
        $query = Guide::query()->where('locale', app()->getLocale());

        if (! $this->boolean('all')) {
            return $query->whereIn('id', $this->validated('ids', []));
        }

        $search = trim((string) $this->validated('search')) ?: null;

        return $query
            ->searchedFor($search)
            ->when($this->validated('status'), fn (Builder $guides, string $status) => $guides->where('status', $status))
            ->whereNotIn('id', $this->validated('except', []));
    }
}
