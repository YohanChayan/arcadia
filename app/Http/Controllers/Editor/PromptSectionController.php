<?php

namespace App\Http\Controllers\Editor;

use App\Actions\MoveContent;
use App\Http\Requests\PromptSectionRequest;
use App\Models\PromptSection;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use Illuminate\Http\RedirectResponse;

/**
 * The sections of a template's latest version, added, edited, deleted and reordered. A frozen
 * version answers 409, and a section of any other version is a 404 here, whatever its id.
 */
class PromptSectionController
{
    public function store(PromptSectionRequest $request, PromptTemplate $template): RedirectResponse
    {
        $version = $this->editable($template);

        $version->sections()->create([
            ...$request->validated(),
            'position' => (int) $version->sections()->max('position') + 1,
        ]);

        return back();
    }

    public function update(PromptSectionRequest $request, PromptTemplate $template, int $section): RedirectResponse
    {
        $this->find($template, $section)->update($request->validated());

        return back();
    }

    public function destroy(PromptTemplate $template, int $section): RedirectResponse
    {
        $this->find($template, $section)->delete();

        return back();
    }

    public function move(PromptTemplate $template, int $section, string $direction, MoveContent $move): RedirectResponse
    {
        $move->handle($this->find($template, $section), 'version_id', $direction === 'up');

        return back();
    }

    private function editable(PromptTemplate $template): PromptVersion
    {
        $version = $template->latestVersion();
        abort_if($version->isFrozen(), 409);

        return $version;
    }

    private function find(PromptTemplate $template, int $id): PromptSection
    {
        return $this->editable($template)->sections()->findOrFail($id);
    }
}
