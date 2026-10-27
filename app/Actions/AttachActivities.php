<?php

namespace App\Actions;

use App\Enums\ActivityFamily;
use App\Models\Version;
use App\Services\ActivityImport;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An AI's checked answer (`ActivityImport`) attached to a draft's nodes, by their keys — the
 * nodes themselves untouched: only their `activity` is written. Its family's activities already on
 * the draft are replaced as a whole, so importing a family again leaves no stale one behind; every
 * other family stays as it was. All at once, or not at all.
 *
 * Only each family's own fields are kept. A flip card is given its own key here, never the AI's:
 * a reader's "not yet" is kept against it, across versions.
 */
class AttachActivities
{
    /** The fields each family keeps — evidence included, for the owner. */
    private const FIELDS = [
        'quick_check' => ['questions' => ['prompt', 'options', 'correct', 'explanation', 'evidence']],
        'stage_game' => ['game', 'instructions', 'pairs' => ['left', 'right'], 'steps', 'evidence'],
        'flip_cards' => ['cards' => ['front', 'back', 'evidence']],
        'practice_extras' => ['hint', 'example', 'rate'],
    ];

    public function __construct(private readonly ActivityImport $import) {}

    /** @param  array<string, mixed>  $data  an answer `ActivityImport::problems()` found nothing wrong with */
    public function handle(Version $draft, array $data): void
    {
        $family = ActivityFamily::from($data['family']);
        $nodes = $this->import->nodes($family, $draft)->keyBy('key');

        DB::transaction(function () use ($family, $nodes, $data) {
            $nodes->filter(fn ($node) => ($node->activity['family'] ?? null) === $family->value)
                ->each(fn ($node) => $node->forceFill(['activity' => null])->save());

            foreach ($data['activities'] as $activity) {
                $nodes[$activity['key']]->forceFill(['activity' => $this->kept($family, $activity)])->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    private function kept(ActivityFamily $family, array $activity): array
    {
        $kept = ['family' => $family->value];

        foreach (self::FIELDS[$family->value] as $list => $fields) {
            if (is_int($list)) {
                // An AI's "" is nothing.
                if (filled($activity[$fields] ?? null)) {
                    $kept[$fields] = $activity[$fields];
                }

                continue;
            }

            $kept[$list] = array_map(fn (array $entry) => array_filter(Arr::only($entry, $fields), fn ($value) => $value !== ''), $activity[$list] ?? []);
        }

        if ($family === ActivityFamily::FLIP_CARDS) {
            $kept['cards'] = array_map(fn (array $card) => ['key' => Str::lower(Str::random(12)), ...$card], $kept['cards']);
        }

        if ($family === ActivityFamily::PRACTICE_EXTRAS) {
            $kept['rate'] = (bool) ($activity['rate'] ?? true);
        }

        return $kept;
    }
}
