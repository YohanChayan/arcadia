<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Reader;
use App\Models\Role;
use App\Models\Tick;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reader's progress on their account: served with the guide page, ticked and cleared one line
 * at a time, theirs alone — on any device they sign in on, across versions, and back again if
 * they are removed and added back.
 */
class GuideProgressTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $intro;

    private Item $practice;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);

        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'goal' => 'Usar fórmulas', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'byline' => 'El Tío Tech', 'position' => 1]);
        $this->intro = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Intro', 'body' => 'Mira.', 'position' => 1]);
        $this->practice = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu primera tabla', 'body' => 'Con tus gastos.', 'position' => 2]);

        $this->guide->latestVersion()->release();

        [$this->ana, $this->reader] = $this->readerOf($this->guide, 'ana@gmail.com');
    }

    /** @return array{User, Reader} */
    private function readerOf(Guide $guide, string $email): array
    {
        $user = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => $email, 'approved_at' => null]);

        return [$user, $guide->readers()->create(['email' => $email, 'user_id' => $user->id])];
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    private function saveUrl(?Reader $reader = null): string
    {
        return '/es/mis-guias/'.($reader ?? $this->reader)->id.'/progress';
    }

    private function tick(string $key, bool $done = true, ?User $as = null, ?Reader $reader = null)
    {
        return $this->actingAs($as ?? $this->ana)->putJson($this->saveUrl($reader), ['key' => $key, 'done' => $done]);
    }

    public function test_the_page_arrives_with_the_readers_ticks_and_where_to_save_them(): void
    {
        $this->tick($this->intro->key)->assertNoContent();

        $this->actingAs($this->ana)->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guide')
                ->where('progress.ticked', [$this->intro->key])
                ->where('progress.saveUrl', url('/es/mis-guias/'.$this->reader->id.'/progress')));
    }

    public function test_a_line_is_ticked_and_cleared_and_a_repeat_changes_nothing(): void
    {
        $this->tick($this->intro->key)->assertNoContent();
        $this->tick($this->intro->key)->assertNoContent();
        $this->assertSame([$this->intro->key], $this->reader->tickedKeys());

        $this->tick($this->intro->key, done: false)->assertNoContent();
        $this->tick($this->intro->key, done: false)->assertNoContent();
        $this->assertSame([], $this->reader->tickedKeys());
    }

    public function test_no_other_reader_of_the_guide_sees_them(): void
    {
        [$bob, $bobsReader] = $this->readerOf($this->guide, 'bob@gmail.com');

        $this->tick($this->intro->key)->assertNoContent();
        $this->tick($this->practice->key, as: $bob, reader: $bobsReader)->assertNoContent();

        $this->actingAs($bob)->get($this->link())
            ->assertInertia(fn ($page) => $page->where('progress.ticked', [$this->practice->key]));
    }

    public function test_a_reader_ticks_only_through_their_own_assignment(): void
    {
        [$bob] = $this->readerOf($this->guide, 'bob@gmail.com');

        $this->tick($this->intro->key, as: $bob)->assertNotFound();
        $this->assertSame([], $this->reader->tickedKeys());
    }

    public function test_only_a_line_of_the_version_the_reader_can_open_is_accepted(): void
    {
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $added = $draft->stages()->first()->cards()->first()->items()
            ->create(['kind' => ItemKind::PRACTICE, 'title' => 'Aún no publicada', 'body' => '…', 'position' => 3]);

        $this->tick($added->key)->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->tick('nosuchkey')->assertUnprocessable()->assertJsonValidationErrors('key');
    }

    public function test_ticks_survive_a_new_version_on_every_line_that_survived(): void
    {
        $this->tick($this->intro->key)->assertNoContent();

        app(DraftNextVersion::class)->handle($this->guide)->release();

        $this->actingAs($this->ana)->get($this->link())
            ->assertInertia(fn ($page) => $page
                ->where('version', 2)
                ->where('progress.ticked', [$this->intro->key]));
    }

    public function test_a_guide_the_reader_cannot_open_takes_no_ticks(): void
    {
        $this->guide->update(['status' => GuideStatus::DRAFT]);

        $this->tick($this->intro->key)->assertNotFound();
    }

    public function test_a_removed_reader_ticks_nothing_and_finds_their_ticks_when_added_back(): void
    {
        $this->tick($this->intro->key)->assertNoContent();

        $this->reader->delete();
        $this->tick($this->practice->key)->assertNotFound();

        $this->reader->restore();
        $this->actingAs($this->ana)->get($this->link())
            ->assertInertia(fn ($page) => $page->where('progress.ticked', [$this->intro->key]));
    }

    public function test_the_owner_reading_as_is_and_the_preview_save_nothing(): void
    {
        $admin = User::factory()->withRole(Role::ALIAS_ADMIN)
            ->create(['approved_at' => now()]);

        $this->actingAs($admin)->get($this->link())
            ->assertInertia(fn ($page) => $page->where('progress', null));

        $this->actingAs($admin)->get('/es/editor/guides/'.$this->guide->id.'/preview')
            ->assertInertia(fn ($page) => $page->missing('progress'));

        $this->assertSame(0, Tick::count());
    }

    public function test_an_account_with_no_arcadia_role_reaches_no_save(): void
    {
        $stranger = User::factory()->create();

        $this->tick($this->intro->key, as: $stranger)->assertForbidden();
    }

    public function test_the_dashboard_shows_where_the_reader_stands_on_each_guide(): void
    {
        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page
                ->where('guides.0.progress.done', 0)
                ->where('guides.0.progress.total', 2)
                ->where('guides.0.progress.last_ticked_at', null));

        $this->tick($this->intro->key)->assertNoContent();

        $this->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page
                ->where('guides.0.progress.done', 1)
                ->where('guides.0.progress.practices', ['done' => 1, 'total' => 2])
                ->where('guides.0.progress.videos', ['done' => 0, 'total' => 0])
                ->where('guides.0.progress.links', ['done' => 0, 'total' => 0])
                ->where('guides.0.progress.stages', ['done' => 0, 'total' => 1])
                ->whereNot('guides.0.progress.last_ticked_at', null));

        $this->tick($this->practice->key)->assertNoContent();

        $this->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('guides.0.progress.stages', ['done' => 1, 'total' => 1]));

        // A guide with no release yet has nothing to count.
        $draft = Guide::create(['locale' => 'es', 'title' => 'Inglés', 'level' => Level::BASIC, 'status' => GuideStatus::DRAFT]);
        $draft->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id]);

        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->where('guides.1.progress', null));
    }

    public function test_the_dashboard_counts_links_apart_from_videos(): void
    {
        $guide = Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $card = $guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Video', 'url' => 'https://www.youtube.com/watch?v=abc', 'position' => 1]);
        $link = $card->items()->create(['kind' => ItemKind::EXTERNAL_SITE, 'title' => 'Lección', 'url' => 'https://example.com/leccion', 'position' => 2]);
        $guide->latestVersion()->release();

        $reader = $guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id]);
        $this->tick($link->key, reader: $reader)->assertNoContent();

        $this->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page
                ->where('guides.1.progress.videos', ['done' => 0, 'total' => 1])
                ->where('guides.1.progress.links', ['done' => 1, 'total' => 1])
                ->where('guides.1.progress.done', 1));
    }
}
