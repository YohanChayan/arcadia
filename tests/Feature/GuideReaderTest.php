<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Services\GuidePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A guide reaches a reader only by its link, and only as its latest released version of a
 * published guide. Everything else is the same 404, so a link says nothing about what else
 * exists. The owner's preview is the same page, on any version.
 */
class GuideReaderTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        // Absolute guide links take scheme and port from `APP_URL`; pin production's.
        config(['app.url' => 'https://arcadia.yhndevside.com']);

        $this->guide = Guide::create([
            'locale' => 'es',
            'title' => 'Excel para el trabajo',
            'level' => Level::BASIC,
            'status' => GuideStatus::PUBLISHED,
        ]);

        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'goal' => 'Usar fórmulas', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'byline' => 'El Tío Tech', 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Intro', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu primera tabla', 'body' => 'Con tus gastos.', 'position' => 2]);
    }

    private function link(?Guide $guide = null): string
    {
        return '/guia/'.($guide ?? $this->guide)->fresh()->link_token;
    }

    /** A signed-in reader of this guide: since 2026-10-15 the link opens only for one. */
    private function asReader(): static
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->guide->readers()->create(['email' => $reader->email, 'user_id' => $reader->id]);

        return $this->actingAs($reader);
    }

    private function admin(): User
    {
        return User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]);
    }

    public function test_a_published_released_guide_opens_by_its_link(): void
    {
        $this->guide->latestVersion()->release();

        $this->asReader()->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guide')
                ->where('preview', false)
                ->where('noIndex', true)
                ->where('canonicalUrl', 'https://arcadia.yhndevside.com/guia/'.$this->guide->link_token)
                ->where('privacyUrl', '/privacidad')
                ->where('guide.title', 'Excel para el trabajo')
                ->where('guide.level', 'Básico')
                ->where('stages.0.cards.0.items.0.embed', ['video' => 'dQw4w9WgXcQ'])
                ->where('stages.0.cards.0.items.1.embed', null)
                ->where('stages.0.cards.0.items.1.body', 'Con tus gastos.'));
    }

    public function test_nothing_unreleased_or_unpublished_reaches_a_reader(): void
    {
        // Published, but nothing released yet.
        $this->get($this->link())->assertNotFound();

        $this->guide->latestVersion()->release();
        $this->guide->update(['status' => GuideStatus::DRAFT]);
        $this->get($this->link())->assertNotFound();

        $this->get('/guia/'.str_repeat('a', 24))->assertNotFound();
    }

    public function test_a_reader_gets_the_released_version_not_the_draft_after_it(): void
    {
        $this->guide->latestVersion()->release();
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $draft->stages()->first()->update(['title' => 'Etapa 1, en borrador']);

        $this->asReader()->get($this->link())->assertInertia(fn ($page) => $page
            ->where('version', 1)
            ->where('stages.0.title', 'Etapa 1'));
    }

    public function test_the_other_languages_guide_is_not_served_here(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $english->latestVersion()->release();

        $this->get($this->link($english))->assertNotFound();
    }

    public function test_a_rotated_link_stops_working_and_the_new_one_works(): void
    {
        $this->guide->latestVersion()->release();
        $old = $this->link();

        $this->actingAs($this->admin())
            ->post("/es/editor/guides/{$this->guide->id}/rotate-link")
            ->assertRedirect();

        $this->get($old)->assertNotFound();
        $this->get($this->link())->assertOk();
    }

    public function test_the_owner_previews_any_version_including_a_draft(): void
    {
        $this->actingAs($this->admin())
            ->get("/es/editor/guides/{$this->guide->id}/preview")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guide')
                ->where('preview', true)
                ->where('version', 1)
                ->has('stages', 1));
    }

    public function test_the_guides_list_offers_the_link_and_says_whether_a_reader_could_open_it(): void
    {
        $this->actingAs($this->admin());
        $link = 'https://arcadia.yhndevside.com/guia/'.$this->guide->link_token;

        $this->get('/es/editor/guides')->assertInertia(fn ($page) => $page
            ->where('guides.data.0.link', $link)
            ->where('guides.data.0.readable', false));

        $this->guide->latestVersion()->release();

        $this->get('/es/editor/guides')->assertInertia(fn ($page) => $page
            ->where('guides.data.0.link', $link)
            ->where('guides.data.0.readable', true));
    }

    public function test_youtube_links_become_player_ids_and_anything_else_does_not(): void
    {
        $embed = fn (ItemKind $kind, string $url) => app(GuidePresenter::class)->embed(new Item(['kind' => $kind, 'url' => $url]));

        $this->assertSame(['video' => 'dQw4w9WgXcQ'], $embed(ItemKind::YOUTUBE_VIDEO, 'https://youtu.be/dQw4w9WgXcQ?t=10'));
        $this->assertSame(['video' => 'dQw4w9WgXcQ'], $embed(ItemKind::YOUTUBE_VIDEO, 'https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=x'));
        $this->assertSame(['video' => 'abcDEF12345'], $embed(ItemKind::YOUTUBE_VIDEO, 'https://www.youtube.com/shorts/abcDEF12345'));
        $this->assertSame(['playlist' => 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'], $embed(ItemKind::YOUTUBE_PLAYLIST, 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'));

        $this->assertNull($embed(ItemKind::YOUTUBE_VIDEO, 'https://vimeo.com/123456'));
        $this->assertNull($embed(ItemKind::YOUTUBE_VIDEO, 'https://www.youtube.com/watch?v=<script>'));
        $this->assertNull($embed(ItemKind::EXTERNAL_SITE, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_a_clipped_video_plays_and_opens_from_its_clip_and_says_so_on_the_line(): void
    {
        $video = Item::query()->where('kind', ItemKind::YOUTUBE_VIDEO)->sole();
        $video->update(['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'Chapter "14:00 Fórmulas"']);
        $this->guide->latestVersion()->release();

        $this->asReader()->get($this->link())->assertInertia(fn ($page) => $page
            ->where('stages.0.cards.0.items.0.embed', ['video' => 'dQw4w9WgXcQ', 'start' => 840, 'end' => 1080])
            ->where('stages.0.cards.0.items.0.clip', ['start' => 840, 'end' => 1080])
            // The stored `t=10` would open YouTube somewhere else: the link follows the clip.
            ->where('stages.0.cards.0.items.0.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=840s')
            // The evidence is for the owner.
            ->missing('stages.0.cards.0.items.0.clip_evidence')
            ->where('stages.0.cards.0.items.1.clip', null));
    }

    public function test_a_clip_with_only_a_start_plays_to_the_end_of_the_video(): void
    {
        $item = new Item(['kind' => ItemKind::YOUTUBE_VIDEO, 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'clip_start' => 163]);

        $this->assertSame(['video' => 'dQw4w9WgXcQ', 'start' => 163], app(GuidePresenter::class)->embed($item));
    }

    public function test_a_line_says_roughly_how_long_it_takes_from_its_clip_else_its_brief(): void
    {
        $video = Item::query()->where('kind', ItemKind::YOUTUBE_VIDEO)->sole();
        $practice = Item::query()->where('kind', ItemKind::PRACTICE)->sole();
        $minutes = function () {
            $version = $this->guide->latestVersion()->fresh();

            return collect(app(GuidePresenter::class)->present($this->guide, $version)['stages'][0]['cards'][0]['items'])->pluck('minutes')->all();
        };

        $this->assertSame([null, null], $minutes());

        // A clip with an end is its own length, whatever the brief says; a part minute is a minute.
        $video->forceFill(['clip_start' => 840, 'clip_end' => 1090, 'brief' => ['duration' => '20 min']])->save();
        $this->assertSame(5, $minutes()[0]);

        foreach (['6 min' => 6, '8 min de lectura' => 8, '3:33' => 4, '—' => null, 'unos minutos' => null] as $duration => $expected) {
            $practice->forceFill(['brief' => ['duration' => $duration]])->save();
            $this->assertSame($expected, $minutes()[1], $duration);
        }
    }
}
