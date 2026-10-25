<?php

namespace App\Http\Controllers\Editor;

use App\Actions\ImportGuideContent;
use App\Http\Requests\ImportRequest;
use App\Http\Resources\ImportPreviewResource;
use App\Models\Guide;
use App\Models\Version;
use App\Services\GuideImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * An AI's answer brought into a guide's draft: first checked and summarised (`preview`, which
 * writes nothing), then written (`store`). Only the latest version, while a draft; a released
 * one answers 409, as every content write does.
 */
class ImportController
{
    public function preview(ImportRequest $request, Guide $guide, GuideImport $import): ImportPreviewResource
    {
        $draft = $this->draft($guide);
        $data = $import->extract($request->answer());

        if ($data === null) {
            return new ImportPreviewResource([
                'problems' => [['place' => '—', 'message' => __('arcadia.editor.import.errors.no_json')]],
                'summary' => null,
                'has_content' => $this->hasContent($draft),
            ]);
        }

        $problems = $import->problems($data);

        return new ImportPreviewResource([
            'problems' => $problems,
            'summary' => $problems === [] ? $import->summary($data) : null,
            'has_content' => $this->hasContent($draft),
        ]);
    }

    public function store(ImportRequest $request, Guide $guide, GuideImport $import, ImportGuideContent $write): RedirectResponse
    {
        $draft = $this->draft($guide);
        $data = $import->extract($request->answer());

        if ($data === null || $import->problems($data) !== []) {
            throw ValidationException::withMessages(['file' => __('arcadia.editor.import.errors.not_valid')]);
        }

        if ($this->hasContent($draft) && ! $request->boolean('replace')) {
            throw ValidationException::withMessages(['replace' => __('arcadia.editor.import.errors.confirm_replace')]);
        }

        $write->handle($guide, $draft, $data, $request->boolean('replace'), $request->boolean('apply_details'));

        return back()->with('success', __('arcadia.editor.import.flash.imported'));
    }

    private function draft(Guide $guide): Version
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
        $version = $guide->latestVersion();
        abort_if($version->isReleased(), 409);

        return $version;
    }

    private function hasContent(Version $draft): bool
    {
        return $draft->stages()->exists() || $draft->sections()->exists();
    }
}
