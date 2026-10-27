<?php

namespace App\Http\Controllers\Editor;

use App\Actions\AttachActivities;
use App\Http\Requests\ActivityImportRequest;
use App\Models\Guide;
use App\Models\Version;
use App\Services\ActivityImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * An AI's answer to an activity prompt, attached to a guide's draft: first checked and summarised
 * against the draft's keys (`preview`, which writes nothing), then attached (`store`). Only the
 * latest version, while a draft; a released one answers 409, as every content write does.
 *
 * Its own flow, beside the content import (`ImportController`) and sharing none of it: that one
 * builds a draft's content, this one adds to what is there.
 */
class ActivityImportController
{
    public function preview(ActivityImportRequest $request, Guide $guide, ActivityImport $import): JsonResponse
    {
        $draft = $this->draft($guide);
        $data = $import->extract($request->answer());

        if ($data === null) {
            return response()->json(['problems' => [['place' => '—', 'message' => __('arcadia.editor.activity_import.errors.no_json')]], 'summary' => null]);
        }

        $problems = $import->problems($data, $draft);

        return response()->json([
            'problems' => $problems,
            'summary' => $problems === [] ? $import->summary($data, $draft) : null,
        ]);
    }

    public function store(ActivityImportRequest $request, Guide $guide, ActivityImport $import, AttachActivities $attach): RedirectResponse
    {
        $draft = $this->draft($guide);
        $data = $import->extract($request->answer());

        if ($data === null || $import->problems($data, $draft) !== []) {
            throw ValidationException::withMessages(['file' => __('arcadia.editor.activity_import.errors.not_valid')]);
        }

        $attach->handle($draft, $data);

        return back()->with('success', __('arcadia.editor.activity_import.flash.imported'));
    }

    private function draft(Guide $guide): Version
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
        $version = $guide->latestVersion();
        abort_if($version->isReleased(), 409);

        return $version;
    }
}
