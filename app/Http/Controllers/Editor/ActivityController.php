<?php

namespace App\Http\Controllers\Editor;

use App\Actions\RewordActivity;
use App\Enums\ContentNode;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's review of a draft's activities, on its content screen: one reworded
 * (`RewordActivity`, its text only) or removed. A released version is read-only (409): a wrong
 * activity there is a new version's job, never fixed in place.
 */
class ActivityController
{
    public function update(Request $request, Guide $guide, string $node, int $id, RewordActivity $reword): RedirectResponse
    {
        $row = $this->find($guide, $node, $id);
        $request->validate(['activity' => ['required', 'array']]);

        $reword->handle($row, $request->input('activity'));

        return back()->with('success', __('arcadia.editor.activities.flash.reworded'));
    }

    public function destroy(Guide $guide, string $node, int $id): RedirectResponse
    {
        $this->find($guide, $node, $id)->forceFill(['activity' => null])->save();

        return back()->with('success', __('arcadia.editor.activities.flash.removed'));
    }

    /** A node of the guide's draft that carries an activity. */
    private function find(Guide $guide, string $node, int $id): Model
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
        $draft = $guide->latestVersion();
        abort_if($draft->isReleased(), 409);

        $row = ContentNode::from($node)->model()::findOrFail($id);
        abort_unless($row->owningVersion()?->id === $draft->id && $row->activity !== null, 404);

        return $row;
    }
}
