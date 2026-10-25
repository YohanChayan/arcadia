<?php

namespace App\Http\Controllers\Editor;

use App\Actions\MoveContent;
use App\Http\Requests\PromptVariableRequest;
use App\Models\PromptTemplate;
use App\Models\PromptVariable;
use App\Models\PromptVersion;
use Illuminate\Http\RedirectResponse;

/**
 * The declared variables of a template's latest version, added, edited, deleted and reordered —
 * the order the fill-in form asks for them in. A used version answers 409, and a variable of
 * any other version is a 404 here.
 */
class PromptVariableController
{
    public function store(PromptVariableRequest $request, PromptTemplate $template): RedirectResponse
    {
        $version = $this->editable($template);

        $version->variables()->create([
            ...$request->validated(),
            'position' => (int) $version->variables()->max('position') + 1,
        ]);

        return back();
    }

    public function update(PromptVariableRequest $request, PromptTemplate $template, int $variable): RedirectResponse
    {
        $this->find($template, $variable)->update($request->validated());

        return back();
    }

    public function destroy(PromptTemplate $template, int $variable): RedirectResponse
    {
        $this->find($template, $variable)->delete();

        return back();
    }

    public function move(PromptTemplate $template, int $variable, string $direction, MoveContent $move): RedirectResponse
    {
        $move->handle($this->find($template, $variable), 'version_id', $direction === 'up');

        return back();
    }

    private function editable(PromptTemplate $template): PromptVersion
    {
        $version = $template->latestVersion();
        abort_if($version->isFrozen(), 409);

        return $version;
    }

    private function find(PromptTemplate $template, int $id): PromptVariable
    {
        return $this->editable($template)->variables()->findOrFail($id);
    }
}
