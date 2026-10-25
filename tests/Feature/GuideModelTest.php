<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Actions\FixItemEverywhere;
use App\Enums\CardKind;
use App\Enums\ChangeKind;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\SectionKind;
use App\Models\Card;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * What the data model promises a reader: progress survives anything the owner does (an item's
 * key is shared by every copy of it and never changes), the version they were given never
 * changes under them (a released version is frozen), and a dead link is still repaired for
 * them (fix-everywhere reaches the frozen copies).
 */
class GuideModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guide_gets_an_unguessable_link_that_can_be_rotated(): void
    {
        $guide = $this->guide();
        $first = $guide->link_token;

        $this->assertMatchesRegularExpression('/^[a-z0-9]{24}$/', $first);
        $this->assertSame('link_token', $guide->getRouteKeyName());

        $guide->rotateLinkToken();

        $this->assertNotSame($first, $guide->fresh()->link_token);
    }

    public function test_the_link_token_cannot_be_chosen_from_input(): void
    {
        $guide = Guide::create(['locale' => 'es', 'title' => 'x', 'level' => Level::BASIC, 'link_token' => 'chosen']);

        $this->assertNotSame('chosen', $guide->link_token);
    }

    public function test_a_guide_starts_with_version_one_as_a_draft(): void
    {
        $guide = $this->guide();

        $this->assertSame(1, $guide->latestVersion()->number);
        $this->assertFalse($guide->latestVersion()->isReleased());
        $this->assertNull($guide->releasedVersion());
    }

    public function test_an_item_key_is_minted_by_the_app_and_never_changes(): void
    {
        $item = $this->item($this->card($this->guide()->latestVersion()));

        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $item->key);

        // Edits and reordering leave it alone.
        $item->update(['title' => 'Renamed', 'position' => 9]);
        $this->assertSame($item->key, $item->fresh()->key);

        $this->expectException(LogicException::class);
        $item->key = 'something-else';
        $item->save();
    }

    public function test_a_key_cannot_be_supplied_through_mass_assignment(): void
    {
        $card = $this->card($this->guide()->latestVersion());
        $item = $card->items()->create(['key' => 'mine', 'kind' => ItemKind::NOTE, 'title' => 'x', 'position' => 1]);

        $this->assertNotSame('mine', $item->key);
    }

    public function test_the_next_version_is_a_full_copy_that_keeps_every_key(): void
    {
        $guide = $this->guide();
        $v1 = $guide->latestVersion();
        $v1->sections()->create(['kind' => SectionKind::HOW_TO_USE, 'body' => 'Mark each video.', 'position' => 1]);
        $card = $this->card($v1);
        $kept = $this->item($card, 'Intro');
        $dropped = $this->item($card, 'Old video', 2);
        $v1->release();

        $v2 = app(DraftNextVersion::class)->handle($guide);

        $this->assertSame(2, $v2->number);
        $this->assertFalse($v2->isReleased());
        $this->assertSame([$kept->key, $dropped->key], $v2->items()->orderBy('position')->pluck('key')->all());
        $this->assertSame(['Mark each video.'], $v2->sections()->pluck('body')->all());
        $this->assertNotSame($kept->id, $v2->items()->where('key', $kept->key)->value('id'));

        // The draft may modify, remove and add — and v1 is untouched by any of it.
        $v2->items()->where('key', $kept->key)->first()->update(['title' => 'Intro, rewritten']);
        $v2->items()->where('key', $dropped->key)->first()->delete();
        $added = $this->item($v2->stages()->first()->cards()->first(), 'New exam', 3);
        $v2->changes()->create(['kind' => ChangeKind::NEW_EXAM, 'title' => 'A final exam']);

        $this->assertSame(['Intro', 'Old video'], $v1->items()->orderBy('position')->pluck('title')->all());
        $this->assertSame(['Intro, rewritten', 'New exam'], $v2->items()->orderBy('position')->pluck('title')->all());
        $this->assertNotContains($added->key, [$kept->key, $dropped->key]);
    }

    public function test_drafting_again_returns_the_draft_in_progress(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->release();

        $first = app(DraftNextVersion::class)->handle($guide);
        $again = app(DraftNextVersion::class)->handle($guide);

        $this->assertTrue($first->is($again));
    }

    public function test_a_reader_gets_the_latest_released_version(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->release();
        app(DraftNextVersion::class)->handle($guide);

        $this->assertSame(1, $guide->releasedVersion()->number);
        $this->assertSame(2, $guide->latestVersion()->number);
    }

    public function test_a_released_version_is_frozen(): void
    {
        $v1 = $this->guide()->latestVersion();
        $item = $this->item($this->card($v1));
        $v1->release();

        foreach ([
            fn () => $item->fresh()->update(['title' => 'Changed']),
            fn () => $item->fresh()->delete(),
            fn () => $v1->stages()->create(['title' => 'Another stage', 'position' => 2]),
        ] as $edit) {
            try {
                $edit();
                $this->fail('A released version accepted an edit.');
            } catch (LogicException) {
                // Frozen, as promised.
            }
        }

        $this->assertSame('Intro', $item->fresh()->title);
    }

    public function test_fix_everywhere_repairs_every_copy_including_released_ones(): void
    {
        $guide = $this->guide();
        $v1 = $guide->latestVersion();
        $dead = $this->item($this->card($v1), 'A video that was taken down');
        $other = $this->item($v1->stages()->first()->cards()->first(), 'Unrelated', 2);
        $v1->release();
        app(DraftNextVersion::class)->handle($guide)->release();

        $repaired = app(FixItemEverywhere::class)->handle($guide, $dead->key, [
            'url' => 'https://www.youtube.com/watch?v=replacement',
            'is_available' => true,
            'key' => 'ignored',
            'kind' => ItemKind::NOTE,
        ]);

        $this->assertSame(2, $repaired);
        $copies = Item::where('key', $dead->key)->get();
        $this->assertCount(2, $copies);
        $this->assertSame(['https://www.youtube.com/watch?v=replacement'], $copies->pluck('url')->unique()->values()->all());
        $this->assertSame([ItemKind::YOUTUBE_VIDEO], $copies->pluck('kind')->unique()->values()->all());
        $this->assertSame('https://www.youtube.com/watch?v=abc', $other->fresh()->url);

        // The exception ends with the repair: released versions are frozen again.
        $this->assertFalse(Version::isFixing());
        $this->expectException(LogicException::class);
        $other->fresh()->update(['title' => 'Changed']);
    }

    public function test_a_repair_that_changes_the_link_clears_its_clip_in_every_copy_unless_it_sets_one(): void
    {
        $guide = $this->guide();
        $v1 = $guide->latestVersion();
        $video = $this->item($this->card($v1));
        $video->update(['clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'Chapter "14:00 Fórmulas"']);
        $v1->release();
        app(DraftNextVersion::class)->handle($guide);
        $fix = fn (array $repair) => app(FixItemEverywhere::class)->handle($guide, $video->key, $repair);
        $clips = fn () => Item::where('key', $video->key)->get()->map->only(Item::CLIP_FIELDS)->unique()->values()->all();

        // The same link: the clip is still that video's.
        $fix(['title' => 'Renamed', 'url' => 'https://www.youtube.com/watch?v=abc']);
        $this->assertSame([['clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'Chapter "14:00 Fórmulas"']], $clips());

        // Another video: the old minutes mean nothing on it, in the released copy too.
        $fix(['url' => 'https://www.youtube.com/watch?v=replacement']);
        $this->assertSame([['clip_start' => null, 'clip_end' => null, 'clip_evidence' => null]], $clips());

        // A repair that brings its own clip keeps it.
        $fix(['url' => 'https://www.youtube.com/watch?v=third', 'clip_start' => 60, 'clip_end' => 300, 'clip_evidence' => 'Transcript at 1:00']);
        $this->assertSame([['clip_start' => 60, 'clip_end' => 300, 'clip_evidence' => 'Transcript at 1:00']], $clips());
    }

    public function test_levels_are_labelled_in_both_languages(): void
    {
        app()->setLocale('es');
        $this->assertSame('Intermedio', Level::INTERMEDIATE->label());

        app()->setLocale('en');
        $this->assertSame('Intermediate', Level::INTERMEDIATE->label());
    }

    private function guide(): Guide
    {
        return Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function card(Version $version): Card
    {
        $stage = $version->stages()->first()
            ?? $version->stages()->create(['title' => 'Etapa 1', 'goal' => 'Al terminar…', 'position' => 1]);

        return $stage->cards()->create(['kind' => CardKind::SOURCE, 'byline' => 'El Tío Tech', 'position' => 1]);
    }

    private function item(Card $card, string $title = 'Intro', int $position = 1): Item
    {
        return $card->items()->create([
            'kind' => ItemKind::YOUTUBE_VIDEO,
            'title' => $title,
            'url' => 'https://www.youtube.com/watch?v=abc',
            'position' => $position,
        ]);
    }
}
