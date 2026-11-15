<?php

namespace App\Http\Controllers\Editor;

use App\Actions\Guides\ListGuide;
use App\Actions\Guides\PublishGuide;
use App\Enums\GuideBulkAction;
use App\Enums\GuideListing;
use App\Enums\GuidePublishing;
use App\Enums\GuideStatus;
use App\Http\Requests\BulkGuideRequest;
use App\Models\Guide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * One action on many guides from the guides list: publish, take down, list in the catalogue,
 * keep out of it, or delete.
 *
 * `preview` answers, before the owner confirms, what the action would reach — for publishing,
 * which drafts it would release and which guides it must leave out for having no content; for
 * listing, which appear at once and which only once published. `apply` then does it, guide by guide, so each takes the same path one guide does.
 */
class BulkGuideController
{
    /** At most this many titles named per group in the confirmation; the rest are counted. */
    private const NAMED = 8;

    public function preview(BulkGuideRequest $request, PublishGuide $publish, ListGuide $list): JsonResponse
    {
        $guides = $request->guides()->orderBy('title')->get(['id', 'title', 'status', 'locale', 'open_for_enrollment', 'in_catalogue']);

        return match ($request->action()) {
            GuideBulkAction::PUBLISH => $this->grouped($guides, GuidePublishing::cases(), fn (Guide $guide) => $publish->assess($guide)),
            GuideBulkAction::LIST => $this->grouped($guides, GuideListing::cases(), fn (Guide $guide) => $list->assess($guide)),
            default => response()->json(['total' => $guides->count()]),
        };
    }

    public function apply(BulkGuideRequest $request, PublishGuide $publish, ListGuide $list): RedirectResponse
    {
        $guides = $request->guides()->get();

        $message = match ($request->action()) {
            GuideBulkAction::PUBLISH => $this->publish($guides, $publish),
            GuideBulkAction::DRAFT => $this->takeDown($guides),
            GuideBulkAction::LIST => $this->list($guides, $list),
            GuideBulkAction::UNLIST => $this->unlist($guides),
            GuideBulkAction::DELETE => $this->delete($guides),
        };

        return back()->with('success', $message);
    }

    /**
     * The guides grouped by what the action would do to each, every state present even when empty.
     *
     * @param  Collection<int, Guide>  $guides
     * @param  array<int, \BackedEnum>  $states
     * @param  callable(Guide): \BackedEnum  $assess
     */
    private function grouped(Collection $guides, array $states, callable $assess): JsonResponse
    {
        $groups = $guides->groupBy(fn (Guide $guide) => $assess($guide)->value);

        return response()->json([
            'total' => $guides->count(),
            'groups' => collect($states)->mapWithKeys(fn (\BackedEnum $state) => [
                $state->value => [
                    'count' => $groups->get($state->value, collect())->count(),
                    'titles' => $groups->get($state->value, collect())->take(self::NAMED)->pluck('title'),
                ],
            ]),
        ]);
    }

    /** @param  Collection<int, Guide>  $guides */
    private function publish(Collection $guides, PublishGuide $publish): string
    {
        $outcomes = $guides->map(fn (Guide $guide) => $publish->handle($guide))->countBy(fn (GuidePublishing $outcome) => $outcome->value);
        $released = $outcomes->get(GuidePublishing::RELEASES_DRAFT->value, 0);
        $published = $released + $outcomes->get(GuidePublishing::PUBLISHES->value, 0);
        $skipped = $outcomes->get(GuidePublishing::NO_CONTENT->value, 0);

        return collect([
            trans_choice('editor.guides.bulk.flash.published', $published, ['count' => $published]),
            $released ? trans_choice('editor.guides.bulk.flash.released', $released, ['count' => $released]) : null,
            $skipped ? trans_choice('editor.guides.bulk.flash.skipped', $skipped, ['count' => $skipped]) : null,
        ])->filter()->implode(' ');
    }

    /** @param  Collection<int, Guide>  $guides */
    private function takeDown(Collection $guides): string
    {
        $published = $guides->where('status', GuideStatus::PUBLISHED);
        $published->each(fn (Guide $guide) => $guide->update(['status' => GuideStatus::DRAFT]));

        return trans_choice('editor.guides.bulk.flash.drafted', $published->count(), ['count' => $published->count()]);
    }

    /** @param  Collection<int, Guide>  $guides */
    private function list(Collection $guides, ListGuide $list): string
    {
        $outcomes = $guides->map(fn (Guide $guide) => $list->handle($guide))->countBy(fn (GuideListing $outcome) => $outcome->value);
        $listed = $outcomes->get(GuideListing::LISTS->value, 0);
        $waiting = $outcomes->get(GuideListing::AWAITS_PUBLISHING->value, 0);

        return collect([
            trans_choice('editor.guides.bulk.flash.listed', $listed, ['count' => $listed]),
            $waiting ? trans_choice('editor.guides.bulk.flash.awaiting', $waiting, ['count' => $waiting]) : null,
        ])->filter()->implode(' ');
    }

    /**
     * Each kept to its link: still open to whoever has it, out of the catalogue (ADR 0024).
     *
     * @param  Collection<int, Guide>  $guides
     */
    private function unlist(Collection $guides): string
    {
        $listed = $guides->where('in_catalogue', true);
        // One by one, so each is reindexed (`KnowledgeServiceProvider`).
        $listed->each(fn (Guide $guide) => $guide->update(['in_catalogue' => false]));

        return trans_choice('editor.guides.bulk.flash.unlisted', $listed->count(), ['count' => $listed->count()]);
    }

    /** @param  Collection<int, Guide>  $guides */
    private function delete(Collection $guides): string
    {
        // One by one, so each takes its images' files with it (`Guide::booted`).
        $guides->each->delete();

        return trans_choice('editor.guides.bulk.flash.deleted', $guides->count(), ['count' => $guides->count()]);
    }
}
