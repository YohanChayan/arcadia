<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One action on many guides from the guides list: publish — releasing pending drafts and leaving
 * out guides with no content — set to draft, list in the catalogue or keep out of it, and delete; by ids, or every guide the filter
 * matches, always within the editor's own language.
 */
class GuideBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    private function guide(string $title, string $locale = 'es'): Guide
    {
        return Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC]);
    }

    private function withContent(Guide $guide): Guide
    {
        $guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1])
            ->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Intro', 'url' => 'https://www.youtube.com/watch?v=a', 'position' => 1]);

        return $guide;
    }

    public function test_the_preview_says_what_publishing_does_to_each_guide(): void
    {
        $pending = $this->withContent($this->guide('Borrador con contenido'));
        $released = $this->withContent($this->guide('Lanzada en borrador'));
        $released->latestVersion()->release();
        $ready = $this->withContent($this->guide('Ya publicada'));
        $ready->latestVersion()->release();
        $ready->update(['status' => GuideStatus::PUBLISHED]);
        $empty = $this->guide('Vacía');

        $this->postJson('/es/editor/guides/bulk/preview', ['action' => 'publish', 'ids' => [$pending->id, $released->id, $ready->id, $empty->id]])
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('groups.releases_draft.titles', ['Borrador con contenido'])
            ->assertJsonPath('groups.publishes.titles', ['Lanzada en borrador'])
            ->assertJsonPath('groups.ready.titles', ['Ya publicada'])
            ->assertJsonPath('groups.no_content.titles', ['Vacía']);
    }

    public function test_publishing_releases_pending_drafts_and_leaves_guides_without_content_alone(): void
    {
        $pending = $this->withContent($this->guide('Borrador con contenido'));
        // Published already, with a second draft holding new content: that draft is released too.
        $updated = $this->withContent($this->guide('Con versión nueva'));
        $updated->latestVersion()->release();
        $updated->update(['status' => GuideStatus::PUBLISHED]);
        $updated->versions()->create(['number' => 2]);
        $this->withContent($updated);
        $empty = $this->guide('Vacía');

        $this->post('/es/editor/guides/bulk', ['action' => 'publish', 'ids' => [$pending->id, $updated->id, $empty->id]])
            ->assertRedirect()
            ->assertSessionHas('success', implode(' ', [
                trans_choice('arcadia.editor.guides.bulk.flash.published', 2, ['count' => 2]),
                trans_choice('arcadia.editor.guides.bulk.flash.released', 2, ['count' => 2]),
                trans_choice('arcadia.editor.guides.bulk.flash.skipped', 1, ['count' => 1]),
            ]));

        $this->assertTrue($pending->fresh()->isReadable());
        $this->assertTrue($updated->latestVersion()->isReleased());
        $this->assertSame(2, $updated->releasedVersion()->number);
        $this->assertSame(GuideStatus::DRAFT, $empty->fresh()->status);
        $this->assertFalse($empty->latestVersion()->isReleased());
    }

    public function test_publishing_tells_the_readers_who_were_waiting(): void
    {
        $guide = $this->withContent($this->guide('Excel'));
        $reader = $guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->post('/es/editor/guides/bulk', ['action' => 'publish', 'ids' => [$guide->id]]);

        $this->assertNotNull($reader->fresh()->notified_at);
    }

    public function test_all_matching_reaches_every_guide_the_filter_shows_less_the_unticked(): void
    {
        $guides = collect(range(1, 14))->map(fn (int $n) => $this->guide("Excel {$n}"));
        $other = $this->guide('Word');
        $english = $this->guide('Excel in English', 'en');

        $this->post('/es/editor/guides/bulk', ['action' => 'delete', 'all' => true, 'search' => 'Excel', 'except' => [$guides->first()->id]])
            ->assertSessionHas('success', trans_choice('arcadia.editor.guides.bulk.flash.deleted', 13, ['count' => 13]));

        $this->assertSame(
            [$guides->first()->id, $other->id, $english->id],
            Guide::orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_deleting_takes_each_guides_versions_with_it(): void
    {
        $guide = $this->withContent($this->guide('Excel'));

        $this->post('/es/editor/guides/bulk', ['action' => 'delete', 'ids' => [$guide->id]]);

        $this->assertModelMissing($guide);
        $this->assertSame(0, Version::where('guide_id', $guide->id)->count());
    }

    public function test_setting_to_draft_takes_published_guides_down(): void
    {
        $published = $this->withContent($this->guide('Publicada'));
        $published->latestVersion()->release();
        $published->update(['status' => GuideStatus::PUBLISHED]);
        $draft = $this->guide('Borrador');

        $this->post('/es/editor/guides/bulk', ['action' => 'draft', 'ids' => [$published->id, $draft->id]])
            ->assertSessionHas('success', trans_choice('arcadia.editor.guides.bulk.flash.drafted', 1, ['count' => 1]));

        $this->assertSame(GuideStatus::DRAFT, $published->fresh()->status);
        $this->assertTrue($published->latestVersion()->isReleased());
    }

    private function readable(Guide $guide): Guide
    {
        $this->withContent($guide)->latestVersion()->release();
        $guide->update(['status' => GuideStatus::PUBLISHED]);

        return $guide;
    }

    public function test_the_preview_says_which_guides_listing_shows_at_once(): void
    {
        $closed = $this->readable($this->guide('Cerrada'));
        $listed = $this->readable($this->guide('Ya listada'));
        $listed->update(['open_for_enrollment' => true]);
        $draft = $this->guide('Borrador');

        $this->postJson('/es/editor/guides/bulk/preview', ['action' => 'list', 'ids' => [$closed->id, $listed->id, $draft->id]])
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('groups.lists.titles', ['Cerrada'])
            ->assertJsonPath('groups.awaits_publishing.titles', ['Borrador'])
            ->assertJsonPath('groups.ready.titles', ['Ya listada']);
    }

    public function test_listing_opens_each_guide_and_puts_it_in_the_catalogue(): void
    {
        $linkOnly = $this->readable($this->guide('Solo por enlace'));
        $linkOnly->update(['open_for_enrollment' => true, 'in_catalogue' => false]);
        $draft = $this->guide('Borrador');

        $this->post('/es/editor/guides/bulk', ['action' => 'list', 'ids' => [$linkOnly->id, $draft->id]])
            ->assertSessionHas('success', implode(' ', [
                trans_choice('arcadia.editor.guides.bulk.flash.listed', 1, ['count' => 1]),
                trans_choice('arcadia.editor.guides.bulk.flash.awaiting', 1, ['count' => 1]),
            ]));

        $this->assertSame([$linkOnly->id], Guide::listed()->pluck('id')->all());
        $this->assertTrue($draft->fresh()->open_for_enrollment);
        $this->assertTrue($draft->fresh()->in_catalogue);
    }

    public function test_unlisting_keeps_each_guide_open_to_its_link(): void
    {
        $guide = $this->readable($this->guide('Listada'));
        $guide->update(['open_for_enrollment' => true]);

        $this->post('/es/editor/guides/bulk', ['action' => 'unlist', 'ids' => [$guide->id]])
            ->assertSessionHas('success', trans_choice('arcadia.editor.guides.bulk.flash.unlisted', 1, ['count' => 1]));

        $this->assertFalse(Guide::listed()->exists());
        $this->assertTrue($guide->fresh()->open_for_enrollment);
    }

    public function test_a_guide_of_the_other_language_is_never_reached(): void
    {
        $english = $this->guide('Excel', 'en');

        $this->post('/es/editor/guides/bulk', ['action' => 'delete', 'ids' => [$english->id]])
            ->assertSessionHas('success', trans_choice('arcadia.editor.guides.bulk.flash.deleted', 0, ['count' => 0]));

        $this->assertModelExists($english);
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $guide = $this->guide('Excel');

        $this->postJson('/es/editor/guides/bulk', ['action' => 'archive', 'ids' => [$guide->id]])
            ->assertJsonValidationErrors('action');
    }
}
