<?php

namespace App\Http\Controllers\Editor;

use App\Actions\DraftNextVersion;
use App\Actions\FixItemEverywhere;
use App\Actions\TellReaders;
use App\Enums\ChangeKind;
use App\Enums\GuideStatus;
use App\Http\Requests\ItemFixRequest;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;
use App\Models\VersionChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A guide's versions: release the draft, draft the next one, keep its change log, and repair a
 * dead line in every version at once.
 *
 * Release and the change log act on the latest version only while it is a draft. Fix-everywhere
 * is the exception that reaches released versions too: a buyer's copy must not rot.
 */
class VersionController
{
    /**
     * Releasing a version is the owner saying this content is ready, so a guide still in draft is
     * published with it — otherwise the list kept saying "Borrador" and no reader could open what
     * had just been released. Status stays its own switch for taking a guide down again; a guide
     * set back to draft is left there by later releases.
     */
    public function release(Guide $guide, TellReaders $tell): RedirectResponse
    {
        $version = $this->draft($guide);
        $firstRelease = ! $guide->versions()->whereNotNull('released_at')->exists();
        $version->release();

        // Readers added while it could not be opened are told now that it can.
        if ($firstRelease && $guide->status === GuideStatus::DRAFT) {
            $guide->update(['status' => GuideStatus::PUBLISHED]);
            $tell->waiting($guide);

            return back()->with('success', __('arcadia.editor.versions.flash.released_and_published', ['number' => $version->number]));
        }

        $tell->waiting($guide);

        return back()->with('success', __('arcadia.editor.versions.flash.released', ['number' => $version->number]));
    }

    public function store(Guide $guide, DraftNextVersion $draftNext): RedirectResponse
    {
        $this->ownLocale($guide);
        $draft = $draftNext->handle($guide);

        return redirect()
            ->route(app()->getLocale().'.editor.content.show', ['guide' => $guide->id])
            ->with('success', __('arcadia.editor.versions.flash.drafted', ['number' => $draft->number]));
    }

    public function storeChange(Request $request, Guide $guide): RedirectResponse
    {
        $version = $this->draft($guide);

        $version->changes()->create($request->validate([
            'kind' => ['required', Rule::enum(ChangeKind::class)],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'title.required' => __('arcadia.editor.content.errors.title'),
        ]));

        return back();
    }

    public function destroyChange(Guide $guide, VersionChange $change): RedirectResponse
    {
        $version = $this->draft($guide);
        abort_unless($change->version_id === $version->id, 404);
        $change->delete();

        return back();
    }

    public function fix(ItemFixRequest $request, Guide $guide, string $key, FixItemEverywhere $fix): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless(Item::where('key', $key)->whereIn('version_id', $guide->versions()->select('id'))->exists(), 404);

        $count = $fix->handle($guide, $key, $request->validated());

        return back()->with('success', trans_choice('arcadia.editor.versions.flash.fixed', $count, ['count' => $count]));
    }

    private function draft(Guide $guide): Version
    {
        $this->ownLocale($guide);
        $version = $guide->latestVersion();
        abort_if($version->isReleased(), 409);

        return $version;
    }

    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }
}
