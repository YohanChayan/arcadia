<?php

namespace App\Http\Controllers\Editor;

use App\Actions\Guides\PublishGuide;
use App\Enums\GuideBulkAction;
use App\Enums\GuidePublishing;
use App\Enums\GuideStatus;
use App\Http\Requests\BulkGuideRequest;
use App\Models\Guide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * One action on many guides from the guides list: publish, take down, or delete.
 *
 * `preview` answers, before the owner confirms, what the action would reach — and for
 * publishing, which drafts it would release and which guides it must leave out for having no
 * content. `apply` then does it, guide by guide, so each takes the same path one guide does.
 */
class BulkGuideController
{
    /** At most this many titles named per group in the confirmation; the rest are counted. */
    private const NAMED = 8;

    public function preview(BulkGuideRequest $request, PublishGuide $publish): JsonResponse
    {
        $guides = $request->guides()->orderBy('title')->get(['id', 'title', 'status', 'locale']);

        if ($request->action() !== GuideBulkAction::PUBLISH) {
            return response()->json(['total' => $guides->count()]);
        }

        $groups = $guides->groupBy(fn (Guide $guide) => $publish->assess($guide)->value);

        return response()->json([
            'total' => $guides->count(),
            'groups' => collect(GuidePublishing::cases())->mapWithKeys(fn (GuidePublishing $state) => [
                $state->value => [
                    'count' => $groups->get($state->value, collect())->count(),
                    'titles' => $groups->get($state->value, collect())->take(self::NAMED)->pluck('title'),
                ],
            ]),
        ]);
    }

    public function apply(BulkGuideRequest $request, PublishGuide $publish): RedirectResponse
    {
        $guides = $request->guides()->get();

        $message = match ($request->action()) {
            GuideBulkAction::PUBLISH => $this->publish($guides, $publish),
            GuideBulkAction::DRAFT => $this->takeDown($guides),
            GuideBulkAction::DELETE => $this->delete($guides),
        };

        return back()->with('success', $message);
    }

    /** @param  Collection<int, Guide>  $guides */
    private function publish(Collection $guides, PublishGuide $publish): string
    {
        $outcomes = $guides->map(fn (Guide $guide) => $publish->handle($guide))->countBy(fn (GuidePublishing $outcome) => $outcome->value);
        $released = $outcomes->get(GuidePublishing::RELEASES_DRAFT->value, 0);
        $published = $released + $outcomes->get(GuidePublishing::PUBLISHES->value, 0);
        $skipped = $outcomes->get(GuidePublishing::NO_CONTENT->value, 0);

        return collect([
            trans_choice('arcadia.editor.guides.bulk.flash.published', $published, ['count' => $published]),
            $released ? trans_choice('arcadia.editor.guides.bulk.flash.released', $released, ['count' => $released]) : null,
            $skipped ? trans_choice('arcadia.editor.guides.bulk.flash.skipped', $skipped, ['count' => $skipped]) : null,
        ])->filter()->implode(' ');
    }

    /** @param  Collection<int, Guide>  $guides */
    private function takeDown(Collection $guides): string
    {
        $published = $guides->where('status', GuideStatus::PUBLISHED);
        $published->each(fn (Guide $guide) => $guide->update(['status' => GuideStatus::DRAFT]));

        return trans_choice('arcadia.editor.guides.bulk.flash.drafted', $published->count(), ['count' => $published->count()]);
    }

    /** @param  Collection<int, Guide>  $guides */
    private function delete(Collection $guides): string
    {
        // One by one, so each takes its images' files with it (`Guide::booted`).
        $guides->each->delete();

        return trans_choice('arcadia.editor.guides.bulk.flash.deleted', $guides->count(), ['count' => $guides->count()]);
    }
}
