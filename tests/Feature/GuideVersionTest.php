<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The version controls on the editor: release a draft, draft the next one, keep its change
 * log, look at an earlier one, and fix a dead line in every version — released ones included.
 */
class GuideVersionTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->item = $card->items()->create([
            'kind' => ItemKind::YOUTUBE_VIDEO,
            'title' => 'Intro',
            'url' => 'https://www.youtube.com/watch?v=dead',
            'position' => 1,
        ]);
    }

    private function url(string $path): string
    {
        return "/es/editor/guides/{$this->guide->id}{$path}";
    }

    public function test_the_first_release_publishes_a_draft_guide_and_a_later_one_leaves_its_status_alone(): void
    {
        $this->assertSame(GuideStatus::DRAFT, $this->guide->fresh()->status);

        $this->post($this->url('/versions/release'))
            ->assertSessionHas('success', __('arcadia.editor.versions.flash.released_and_published', ['number' => 1]));
        $this->assertSame(GuideStatus::PUBLISHED, $this->guide->fresh()->status);

        // Taken down again by hand, then a new version released: it stays down.
        $this->guide->update(['status' => GuideStatus::DRAFT]);
        $this->post($this->url('/versions'));
        $this->post($this->url('/versions/release'))
            ->assertSessionHas('success', __('arcadia.editor.versions.flash.released', ['number' => 2]));
        $this->assertSame(GuideStatus::DRAFT, $this->guide->fresh()->status);
    }

    public function test_the_draft_is_released_once(): void
    {
        $this->post($this->url('/versions/release'))->assertRedirect();
        $this->assertTrue($this->guide->latestVersion()->isReleased());

        $this->post($this->url('/versions/release'))->assertStatus(409);
    }

    public function test_the_next_version_is_drafted_and_opened(): void
    {
        $this->post($this->url('/versions/release'));

        $this->post($this->url('/versions'))
            ->assertRedirect(route('es.editor.content.show', ['guide' => $this->guide->id]));

        $this->get($this->url('/content'))->assertInertia(fn ($page) => $page
            ->where('version.number', 2)
            ->where('version.latest', true)
            ->where('version.released', false)
            ->has('versions', 2)
            ->where("copies.{$this->item->key}", 2));
    }

    public function test_an_earlier_version_is_shown_read_only(): void
    {
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));

        $this->get($this->url('/content?version=1'))->assertInertia(fn ($page) => $page
            ->where('version.number', 1)
            ->where('version.latest', false));

        $this->get($this->url('/content?version=9'))->assertNotFound();
    }

    public function test_the_change_log_is_kept_on_the_draft_only(): void
    {
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));

        $this->post($this->url('/versions/changes'), ['kind' => 'new_exam', 'title' => 'Un examen final'])->assertRedirect();
        $change = $this->guide->latestVersion()->changes()->sole();
        $this->assertSame('Un examen final', $change->title);

        $this->post($this->url('/versions/release'));

        // Released: the log is frozen with the version.
        $this->post($this->url('/versions/changes'), ['kind' => 'other', 'title' => 'Tarde'])->assertStatus(409);
        $this->delete($this->url("/versions/changes/{$change->id}"))->assertStatus(409);
    }

    public function test_fix_everywhere_reaches_the_released_copy(): void
    {
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));

        $this->put($this->url("/items/{$this->item->key}/fix"), [
            'title' => 'Intro (nuevo enlace)',
            'url' => 'https://www.youtube.com/watch?v=alive',
            'is_available' => true,
            'kind' => 'note',
        ])->assertRedirect();

        $copies = Item::where('key', $this->item->key)->get();
        $this->assertCount(2, $copies);
        $this->assertSame(['https://www.youtube.com/watch?v=alive'], $copies->pluck('url')->unique()->values()->all());
        $this->assertSame([ItemKind::YOUTUBE_VIDEO], $copies->pluck('kind')->unique()->values()->all());
    }

    public function test_fix_everywhere_only_reaches_this_guides_lines(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Otra', 'level' => Level::BASIC]);

        $this->put("/es/editor/guides/{$other->id}/items/{$this->item->key}/fix", ['title' => 'x'])->assertNotFound();
        $this->put($this->url('/items/nosuchkey/fix'), ['title' => 'x'])->assertNotFound();

        $this->assertSame('Intro', $this->item->fresh()->title);
    }

    public function test_the_compare_screen_defaults_to_the_previous_version(): void
    {
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));
        $this->post($this->url('/versions/changes'), ['kind' => 'fixed', 'title' => 'Enlace nuevo']);
        $this->guide->latestVersion()->items()->first()->update(['url' => 'https://www.youtube.com/watch?v=alive']);

        $this->get($this->url('/compare'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/guides/Compare')
                ->where('from', 1)
                ->where('to', 2)
                ->where('diff.summary.items.changed', 1)
                ->where('logged.0.title', 'Enlace nuevo')
                ->where('logged.0.version', 2)
                ->where('labels.items.youtube_video', 'Video de YouTube'));
    }

    public function test_any_two_versions_can_be_compared_and_unknown_ones_are_not(): void
    {
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));
        $this->post($this->url('/versions/release'));
        $this->post($this->url('/versions'));

        $this->get($this->url('/compare?from=1&to=3'))->assertInertia(fn ($page) => $page->where('from', 1)->where('to', 3));
        $this->get($this->url('/compare?from=1&to=9'))->assertNotFound();

        $english = Guide::create(['locale' => 'en', 'title' => 'Excel', 'level' => Level::BASIC]);
        $this->get("/es/editor/guides/{$english->id}/compare")->assertNotFound();
    }
}
