<?php

namespace App\Http\Controllers\Reader;

use App\Enums\GuideStatus;
use App\Models\Reader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * A reader ticks or clears one line of a guide, from the guide page. One small idempotent write
 * per tap; the page shows the tick at once and takes it back if this fails.
 *
 * Only their own assignment, and only a guide they can open — published, with a released version
 * — and only a line of that version. Anything else is a 404, or a 422 for a line that is not there.
 */
class ProgressController
{
    public function __invoke(Request $request, Reader $reader): Response
    {
        abort_unless($reader->user_id === $request->user()->id, 404);
        abort_unless($reader->guide->locale === app()->getLocale(), 404);
        abort_unless($reader->guide->status === GuideStatus::PUBLISHED, 404);

        $version = $reader->guide->releasedVersion() ?? abort(404);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::exists('guide_items', 'key')->where('version_id', $version->id)],
            'done' => ['required', 'boolean'],
        ]);

        $reader->tick($data['key'], $request->boolean('done'));

        return response()->noContent();
    }
}
