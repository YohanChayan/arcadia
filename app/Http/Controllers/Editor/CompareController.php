<?php

namespace App\Http\Controllers\Editor;

use App\Enums\CardKind;
use App\Enums\ChangeKind;
use App\Enums\ItemKind;
use App\Enums\SectionKind;
use App\Models\Guide;
use App\Models\VersionChange;
use App\Services\VersionDiff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two versions of a guide side by side: what actually changed (`VersionDiff`) beside what the
 * change logs say changed. By default the latest against the one before it; "since version 1" is
 * the same comparison from the base.
 */
class CompareController
{
    public function __invoke(Request $request, Guide $guide, VersionDiff $diff): Response
    {
        abort_unless($guide->locale === app()->getLocale(), 404);

        $latest = $guide->latestVersion();
        $toNumber = $request->integer('to', $latest->number);
        $fromNumber = $request->integer('from', max(1, $toNumber - 1));

        $to = $guide->versions()->where('number', $toNumber)->firstOrFail();
        $from = $guide->versions()->where('number', $fromNumber)->firstOrFail();

        // What the owner logged for every version after `from`, up to `to`.
        $logged = VersionChange::query()
            ->whereIn('version_id', $guide->versions()->whereBetween('number', [$from->number + 1, $to->number])->select('id'))
            ->with('version:id,number')
            ->get()
            ->map(fn (VersionChange $change) => [
                ...$change->only('id', 'title', 'notes'),
                'kind' => $change->kind->value,
                'version' => $change->version->number,
            ]);

        return Inertia::render('editor/guides/Compare', [
            'guide' => $guide->only('id', 'title'),
            'versions' => $guide->versions()->get(['id', 'number', 'released_at']),
            'from' => $from->number,
            'to' => $to->number,
            'diff' => $diff->between($from, $to),
            'logged' => $logged,
            'labels' => [
                'sections' => $this->labels(SectionKind::cases(), 'section_kinds'),
                'cards' => $this->labels(CardKind::cases(), 'card_kinds'),
                'items' => $this->labels(ItemKind::cases(), 'item_kinds'),
                'changes' => $this->labels(ChangeKind::cases(), 'change_kinds'),
            ],
        ]);
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return array<string, string>
     */
    private function labels(array $cases, string $group): array
    {
        $labels = [];
        foreach ($cases as $case) {
            $labels[$case->value] = __("arcadia.editor.content.{$group}.{$case->value}");
        }

        return $labels;
    }
}
