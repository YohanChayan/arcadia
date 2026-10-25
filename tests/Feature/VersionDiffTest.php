<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Enums\CardKind;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\SectionKind;
use App\Models\Guide;
use App\Models\Version;
use App\Services\VersionDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comparing two versions matches nodes by key, never by place or text: a renamed stage is that
 * stage renamed, a video moved to another card is that video moved, and one item moving up does
 * not mark every item after it.
 */
class VersionDiffTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Version $v1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC]);
        $this->v1 = $this->guide->latestVersion();

        $this->v1->sections()->create(['kind' => SectionKind::HOW_TO_USE, 'body' => 'Marca cada video.', 'position' => 1]);

        foreach (['Etapa 1', 'Etapa 2'] as $s => $stageTitle) {
            $stage = $this->v1->stages()->create(['title' => $stageTitle, 'position' => $s + 1]);

            foreach (['A', 'B'] as $c => $cardName) {
                $card = $stage->cards()->create(['kind' => CardKind::SOURCE, 'byline' => "{$stageTitle} {$cardName}", 'position' => $c + 1]);

                foreach (['1', '2', '3'] as $i => $n) {
                    $card->items()->create([
                        'kind' => ItemKind::YOUTUBE_VIDEO,
                        'title' => "{$stageTitle}{$cardName}{$n}",
                        'url' => "https://www.youtube.com/watch?v={$s}{$c}{$i}",
                        'position' => $i + 1,
                    ]);
                }
            }
        }

        $this->v1->release();
    }

    private function v2(): Version
    {
        return app(DraftNextVersion::class)->handle($this->guide);
    }

    private function diff(Version $to): array
    {
        return app(VersionDiff::class)->between($this->v1, $to);
    }

    /** Every node at a level, flattened from the tree. */
    private function nodes(array $diff, string $level): array
    {
        $cards = collect($diff['stages'])->flatMap(fn ($stage) => $stage['children']);

        return match ($level) {
            'sections' => $diff['sections'],
            'stages' => $diff['stages'],
            'cards' => $cards->all(),
            'items' => $cards->flatMap(fn ($card) => $card['children'])->all(),
        };
    }

    private function find(array $diff, string $level, string $title): array
    {
        $field = $level === 'cards' ? 'byline' : 'title';

        return collect($this->nodes($diff, $level))->firstWhere("node.{$field}", $title);
    }

    public function test_an_identical_copy_has_no_changes(): void
    {
        $diff = $this->diff($this->v2());

        foreach (['sections', 'stages', 'cards', 'items'] as $level) {
            $this->assertSame(['added' => 0, 'removed' => 0, 'changed' => 0, 'moved' => 0], $diff['summary'][$level], $level);
        }
        $this->assertFalse(collect($diff['stages'])->contains('touched', true));
    }

    public function test_a_renamed_stage_is_changed_not_removed_and_added(): void
    {
        $v2 = $this->v2();
        $v2->stages()->where('title', 'Etapa 1')->first()->update(['title' => 'Primeros pasos']);

        $diff = $this->diff($v2);
        $stage = $this->find($diff, 'stages', 'Primeros pasos');

        $this->assertSame('changed', $stage['status']);
        $this->assertSame(['from' => 'Etapa 1', 'to' => 'Primeros pasos'], $stage['changed']['title']);
        $this->assertSame(['added' => 0, 'removed' => 0, 'changed' => 1, 'moved' => 0], $diff['summary']['stages']);
    }

    public function test_added_removed_and_changed_items_are_told_apart(): void
    {
        $v2 = $this->v2();
        $card = $v2->stages()->where('title', 'Etapa 1')->first()->cards()->where('byline', 'Etapa 1 A')->first();
        $card->items()->where('title', 'Etapa 1A1')->first()->update(['url' => 'https://www.youtube.com/watch?v=new']);
        $card->items()->where('title', 'Etapa 1A2')->first()->delete();
        $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Una práctica', 'position' => 9]);

        $diff = $this->diff($v2);

        $this->assertSame('changed', $this->find($diff, 'items', 'Etapa 1A1')['status']);
        $this->assertSame(['url'], array_keys($this->find($diff, 'items', 'Etapa 1A1')['changed']));
        $this->assertSame('removed', $this->find($diff, 'items', 'Etapa 1A2')['status']);
        $this->assertSame('added', $this->find($diff, 'items', 'Una práctica')['status']);
        $this->assertSame(['added' => 1, 'removed' => 1, 'changed' => 1, 'moved' => 0], $diff['summary']['items']);

        // The removed line is still shown, under the card it left.
        $cardNode = $this->find($diff, 'cards', 'Etapa 1 A');
        $this->assertContains('Etapa 1A2', array_column(array_column($cardNode['children'], 'node'), 'title'));
        $this->assertTrue($cardNode['touched']);
    }

    public function test_an_item_moved_to_another_card_is_moved_not_removed_and_added(): void
    {
        $v2 = $this->v2();
        $to = $v2->stages()->where('title', 'Etapa 2')->first()->cards()->where('byline', 'Etapa 2 B')->first();
        $item = $v2->items()->where('title', 'Etapa 1A1')->first();
        $item->card_id = $to->id;
        $item->save();

        $diff = $this->diff($v2);
        $moved = $this->find($diff, 'items', 'Etapa 1A1');

        $this->assertSame('unchanged', $moved['status']);
        $this->assertTrue($moved['moved']);
        $this->assertContains('Etapa 1A1', array_column(array_column($this->find($diff, 'cards', 'Etapa 2 B')['children'], 'node'), 'title'));
        $this->assertSame(['added' => 0, 'removed' => 0, 'changed' => 0, 'moved' => 1], $diff['summary']['items']);
    }

    public function test_one_item_moving_up_does_not_mark_the_rest_reordered(): void
    {
        $v2 = $this->v2();
        $items = $v2->stages()->where('title', 'Etapa 1')->first()->cards()->where('byline', 'Etapa 1 A')->first()->items;
        // 1, 2, 3 → 3, 1, 2: only the third moved.
        $items->firstWhere('title', 'Etapa 1A3')->update(['position' => 0]);

        $diff = $this->diff($v2);

        $this->assertTrue($this->find($diff, 'items', 'Etapa 1A3')['reordered']);
        $this->assertFalse($this->find($diff, 'items', 'Etapa 1A1')['reordered']);
        $this->assertFalse($this->find($diff, 'items', 'Etapa 1A2')['reordered']);
        $this->assertSame(1, $diff['summary']['items']['moved']);
    }

    public function test_a_removed_stage_brings_its_content_as_removed(): void
    {
        $v2 = $this->v2();
        $v2->stages()->where('title', 'Etapa 2')->first()->delete();

        $diff = $this->diff($v2);
        $stage = $this->find($diff, 'stages', 'Etapa 2');

        $this->assertSame('removed', $stage['status']);
        $this->assertCount(2, $stage['children']);
        $this->assertSame(['removed'], array_values(array_unique(array_column($stage['children'], 'status'))));
        $this->assertSame(['added' => 0, 'removed' => 6, 'changed' => 0, 'moved' => 0], $diff['summary']['items']);
    }

    public function test_a_section_change_is_seen(): void
    {
        $v2 = $this->v2();
        $v2->sections()->first()->update(['body' => 'Marca cada video al terminarlo.']);

        $diff = $this->diff($v2);

        $this->assertSame('changed', $diff['sections'][0]['status']);
        $this->assertSame(['body'], array_keys($diff['sections'][0]['changed']));
    }

    public function test_every_node_keeps_its_key_in_the_copy(): void
    {
        $v2 = $this->v2();

        foreach (['sections', 'stages'] as $relation) {
            $this->assertEqualsCanonicalizing(
                $this->v1->{$relation}()->pluck('key')->all(),
                $v2->{$relation}()->pluck('key')->all(),
                $relation,
            );
        }

        $cardKeys = fn (Version $version) => $version->stages()->with('cards')->get()->flatMap->cards->pluck('key')->all();
        $this->assertEqualsCanonicalizing($cardKeys($this->v1), $cardKeys($v2));
        $this->assertSame($v2->id, $v2->stages()->first()->cards()->first()->version_id);
    }
}
