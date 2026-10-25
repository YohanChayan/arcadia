<?php

namespace App\Actions;

use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;
use App\Services\VideoClip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Writes a checked AI answer (`GuideImport`) into a guide's draft version, all at once: its
 * sections, and stage → card → item, in the answer's order. Every node gets a key minted by the
 * app (`HasLineageKey`); the AI's `ref`s were only there to be checked for duplicates.
 *
 * A draft that already has content is replaced only when asked — never mixed. The greeting and
 * the curator's notes always land on the guide; its title, level and goals only with
 * `$applyDetails`, the goals as the answer writes them (ADR 0020). Nothing is
 * released: the guide stays a draft for the owner to review.
 */
class ImportGuideContent
{
    /**
     * @param  array<string, mixed>  $data  already checked
     */
    public function handle(Guide $guide, Version $draft, array $data, bool $replace, bool $applyDetails): void
    {
        if ($draft->isReleased()) {
            throw new LogicException('A released version is frozen: import into a draft.');
        }

        $hasContent = $draft->stages()->exists() || $draft->sections()->exists();

        if ($hasContent && ! $replace) {
            throw new LogicException('This draft already has content: import replaces it only when asked.');
        }

        DB::transaction(function () use ($guide, $draft, $data, $applyDetails) {
            // Stages take their cards and items with them, by foreign key.
            $draft->stages()->delete();
            $draft->sections()->delete();

            foreach ($data['sections'] ?? [] as $position => $section) {
                $draft->sections()->create([
                    'kind' => $section['kind'],
                    'title' => $this->text($section['title'] ?? null),
                    'body' => $section['body'],
                    'position' => $position + 1,
                ]);
            }

            foreach ($data['stages'] as $s => $stageData) {
                $stage = $draft->stages()->create([
                    'title' => $stageData['title'],
                    'goal' => $this->text($stageData['goal'] ?? null),
                    'position' => $s + 1,
                ]);

                foreach ($stageData['cards'] as $c => $cardData) {
                    $card = $stage->cards()->make([
                        'kind' => $cardData['kind'],
                        'byline' => $this->text($cardData['byline'] ?? null),
                        'description' => $this->text($cardData['description'] ?? null),
                        'quote' => $this->text($cardData['quote'] ?? null),
                        'closing_note' => $this->text($cardData['closing_note'] ?? null),
                        'position' => $c + 1,
                    ]);
                    $card->version_id = $draft->id;
                    $card->save();

                    foreach ($cardData['items'] as $i => $itemData) {
                        $item = $card->items()->make([
                            'kind' => $itemData['kind'],
                            'title' => $itemData['title'],
                            'url' => $this->text($itemData['url'] ?? null),
                            'hint' => $this->text($itemData['hint'] ?? null),
                            'body' => $this->text($itemData['body'] ?? null),
                            'position' => $i + 1,
                            // A clip only on a video, and only with its evidence.
                            ...($itemData['kind'] === ItemKind::YOUTUBE_VIDEO->value ? VideoClip::fromSegment($itemData['segment'] ?? null) : []),
                        ]);
                        $item->brief = $this->brief($itemData);
                        $item->version_id = $draft->id;
                        $item->save();
                    }
                }
            }

            $guide->forceFill([
                'greeting' => $this->text($data['greeting'] ?? null) ?? $guide->greeting,
                'curator_notes' => filled($data['curator_notes'] ?? null) ? $data['curator_notes'] : $guide->curator_notes,
            ]);

            if ($applyDetails) {
                if ($title = $this->text($data['title'] ?? null)) {
                    $guide->title = Str::limit($title, 160, '');
                }
                if ($level = Level::tryFrom($data['level'] ?? '')) {
                    $guide->level = $level;
                }

                // The answer's own goals, as written — nothing to match, nothing dropped.
                if ($goals = Guide::goalLines($data['goals'] ?? [])) {
                    $guide->goals = $goals;
                }
            }

            $guide->save();
        });
    }

    /** An AI writes "" for "nothing": stored as nothing. */
    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A link's brief, its known fields only, empty ones dropped; none for a line that is not a
     * link, or a brief with nothing in it.
     *
     * @param  array<string, mixed>  $itemData
     * @return array<string, mixed>|null
     */
    private function brief(array $itemData): ?array
    {
        if (! ItemKind::from($itemData['kind'])->isLink() || ! is_array($itemData['brief'] ?? null)) {
            return null;
        }

        $brief = [];
        foreach (Item::BRIEF_FIELDS as $field) {
            $value = $itemData['brief'][$field] ?? null;
            $value = is_array($value)
                ? array_values(array_filter(array_map(fn ($each) => $this->text((string) $each), $value)))
                : $this->text(is_string($value) ? $value : null);

            if (filled($value)) {
                $brief[$field] = $value;
            }
        }

        return $brief ?: null;
    }
}
