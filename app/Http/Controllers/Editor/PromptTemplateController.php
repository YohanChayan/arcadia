<?php

namespace App\Http\Controllers\Editor;

use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Http\Requests\PromptTemplateRequest;
use App\Models\PromptTemplate;
use App\Services\PromptFormOptions;
use App\Services\PromptRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's AI research prompts: listed, named, opened on a version, deleted.
 *
 * Shared by both languages' editors: one template serves every language, the language being a
 * variable (option PT3). Only the latest version is edited, and only while no guide has been
 * produced from it; earlier versions open read-only with `?version=N`.
 */
class PromptTemplateController
{
    public function index(): Response
    {
        return Inertia::render('editor/prompts/Index', [
            'templates' => PromptTemplate::query()
                ->withCount('versions')
                ->withMax('versions as latest_number', 'number')
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function store(PromptTemplateRequest $request): RedirectResponse
    {
        $template = PromptTemplate::create($request->validated());

        return to_route(app()->getLocale().'.editor.prompts.show', $template);
    }

    public function show(Request $request, PromptTemplate $template, PromptFormOptions $options, PromptRenderer $renderer): Response
    {
        $latest = $template->latestVersion();
        $version = $request->filled('version')
            ? $template->versions()->where('number', $request->integer('version'))->firstOrFail()
            : $latest;

        return Inertia::render('editor/prompts/Show', [
            'template' => $template->only('id', 'name', 'description'),
            'version' => [
                'id' => $version->id,
                'number' => $version->number,
                'description' => $version->description,
                'frozen' => $version->isFrozen(),
                'latest' => $version->is($latest),
                'editable' => $version->is($latest) && ! $version->isFrozen(),
            ],
            'versions' => $template->versions()->get(['id', 'number', 'description', 'frozen_at']),
            'sections' => $version->sections,
            'variables' => $version->variables,
            // Placeholders the sections use that no variable declares: they would be copied as
            // `{{name}}`, so the screen names them.
            'undeclared' => array_values(array_diff($renderer->placeholders($version), $version->variables->pluck('name')->all())),
            'kinds' => array_map(fn (PromptSectionKind $kind) => [
                'value' => $kind->value,
                'label' => __("arcadia.editor.prompts.kinds.{$kind->value}"),
            ], PromptSectionKind::cases()),
            'types' => array_map(fn (PromptVariableType $type) => [
                'value' => $type->value,
                'label' => __("arcadia.editor.prompts.types.{$type->value}"),
            ], PromptVariableType::cases()),
            // For the "Probar" panel, which fills this version like a request's popup does.
            'fillVersion' => $options->version($version),
            'choices' => $options->choices(app()->getLocale()),
        ]);
    }

    public function update(PromptTemplateRequest $request, PromptTemplate $template): RedirectResponse
    {
        $template->update($request->validated());

        return back()->with('success', __('arcadia.editor.prompts.flash.saved'));
    }

    /** A template a guide was produced from keeps that guide's provenance: it is not deleted. */
    public function destroy(PromptTemplate $template): RedirectResponse
    {
        abort_if($template->versions()->whereNotNull('frozen_at')->exists(), 409);

        $template->delete();

        return to_route(app()->getLocale().'.editor.prompts.index')
            ->with('success', __('arcadia.editor.prompts.flash.deleted'));
    }
}
