<?php

namespace App\Http\Controllers\Editor;

use App\Actions\AppendContent;
use App\Actions\MoveContent;
use App\Enums\ActivityFamily;
use App\Enums\CardKind;
use App\Enums\ChangeKind;
use App\Enums\ContentNode;
use App\Enums\ItemKind;
use App\Enums\SectionKind;
use App\Http\Requests\ContentNodeRequest;
use App\Models\Guide;
use App\Models\Item;
use App\Models\PromptTemplate;
use App\Models\Setting;
use App\Models\Version;
use App\Services\ArcadiaSite;
use App\Services\PromptFormOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A guide's content tree — sections, and stage → card → item — on its latest version, or on an
 * earlier one picked with `?version=N`, read-only. The screen also carries the research prompt the
 * guide is researched with, in whichever version of it the owner picks.
 *
 * Only the latest version, while a draft, can be edited: a released version is frozen
 * (`FrozenOnceReleased`) and the screen shows it read-only. Every write is checked against this guide's draft, so an id from
 * another guide, or from a released version, is a 404 rather than a quiet edit somewhere else.
 */
class ContentController
{
    public function show(Request $request, Guide $guide, ArcadiaSite $site, PromptFormOptions $options): Response
    {
        $this->ownLocale($guide);
        $latest = $guide->latestVersion();
        $version = $request->filled('version')
            ? $guide->versions()->where('number', $request->integer('version'))->firstOrFail()
            : $latest;

        return Inertia::render('editor/guides/Content', [
            // The link and whether it opens yet, for the dialog shown once a version is released.
            'guide' => [
                ...$guide->only('id', 'title'),
                'link' => $site->guideUrl($guide->link_token, $guide->locale),
                'readable' => $guide->isReadable(),
            ],
            'version' => [
                'id' => $version->id,
                'number' => $version->number,
                'released' => $version->isReleased(),
                'released_at' => $version->released_at,
                'latest' => $version->is($latest),
            ],
            'versions' => $guide->versions()->get(['id', 'number', 'released_at']),
            'changes' => $version->changes()->get(),
            'sections' => $version->sections()->get(),
            'stages' => $version->stages()->with(['cards.items'])->get(),
            // How many versions carry each line, so a fix-everywhere says what it will touch.
            'copies' => Item::query()
                ->whereIn('version_id', $guide->versions()->select('id'))
                ->select('key')
                ->selectRaw('count(*) as copies')
                ->groupBy('key')
                ->pluck('copies', 'key'),
            'kinds' => [
                'sections' => $this->options(SectionKind::cases(), 'section_kinds'),
                'cards' => $this->options(CardKind::cases(), 'card_kinds'),
                'items' => $this->options(ItemKind::cases(), 'item_kinds'),
                'changes' => $this->options(ChangeKind::cases(), 'change_kinds'),
            ],
            // How long a step should take, for a line's replacement prompt: what this guide's
            // research prompt was filled with, else this language's setting.
            'stepLength' => $guide->prompt_values['step_minutes'] ?? Setting::forLocale($guide->locale)->stepLengthLabel(),
            // The research prompt in every version, to research the guide again: it opens on the
            // version last copied for this guide, filled as it was — or, never copied, on the latest,
            // filled from the guide and its request. Copying records it on the guide.
            'research' => [
                'prompt' => $options->template(PromptTemplate::RESEARCH),
                'versionId' => $guide->prompt_version_id,
                'values' => $options->researchValues($guide),
            ],
            // The activity prompts there are, each filled from the guide — its content put in by
            // the server — to bring their answers back with "Importar actividades".
            'activityPrompts' => collect(ActivityFamily::cases())
                ->filter(fn (ActivityFamily $family) => $family->template() !== null)
                ->map(fn (ActivityFamily $family) => [
                    'family' => $family->value,
                    'label' => $family->label(),
                    'prompt' => $options->template($family->template()),
                    'values' => ['title' => $guide->title, 'level' => $guide->level->value, 'language' => $guide->locale],
                ])
                ->filter(fn (array $activity) => $activity['prompt'] !== null)
                ->values(),
            'choices' => $options->choices($guide->locale),
            'linkKinds' => array_values(array_map(
                fn (ItemKind $kind) => $kind->value,
                array_filter(ItemKind::cases(), fn (ItemKind $kind) => $kind->isLink()),
            )),
        ]);
    }

    public function store(ContentNodeRequest $request, Guide $guide, string $node, AppendContent $append): RedirectResponse
    {
        $append->handle(
            $this->draft($guide),
            ContentNode::from($node),
            $request->validated('parent_id'),
            $request->safe()->except('parent_id'),
        );

        return back();
    }

    public function update(ContentNodeRequest $request, Guide $guide, string $node, int $id): RedirectResponse
    {
        $this->find($guide, ContentNode::from($node), $id)->update($request->validated());

        return back();
    }

    public function destroy(Guide $guide, string $node, int $id): RedirectResponse
    {
        // A stage takes its cards and their items with it, a card its items — by foreign key.
        $this->find($guide, ContentNode::from($node), $id)->delete();

        return back();
    }

    public function move(Guide $guide, string $node, int $id, string $direction, MoveContent $move): RedirectResponse
    {
        $kind = ContentNode::from($node);
        $move->handle($this->find($guide, $kind, $id), $kind->parentColumn(), $direction === 'up');

        return back();
    }

    /** The version being edited. A released one is not editable; the screen never offers it. */
    private function draft(Guide $guide): Version
    {
        $this->ownLocale($guide);
        $version = $guide->latestVersion();
        abort_if($version->isReleased(), 409);

        return $version;
    }

    private function find(Guide $guide, ContentNode $node, int $id): Model
    {
        $draft = $this->draft($guide);
        $row = $node->model()::findOrFail($id);
        abort_unless($row->owningVersion()?->id === $draft->id, 404);

        return $row;
    }

    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases, string $labels): array
    {
        return array_map(fn (\BackedEnum $case) => [
            'value' => $case->value,
            'label' => __("arcadia.editor.content.{$labels}.{$case->value}"),
        ], $cases);
    }
}
