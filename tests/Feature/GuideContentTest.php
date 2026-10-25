<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Enums\Level;
use App\Models\Card;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The content tree editor: a guide's draft version is built in place — sections, and stage →
 * card → item — and every write is held to that draft. A released version, another guide's
 * rows, and another language's guide are all out of reach.
 */
class GuideContentTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function url(string $path = ''): string
    {
        return "/es/editor/guides/{$this->guide->id}/content{$path}";
    }

    private function stage(string $title = 'Etapa 1'): Stage
    {
        $this->post($this->url('/stages'), ['title' => $title, 'goal' => 'Usar fórmulas'])->assertRedirect();

        return Stage::latest('id')->first();
    }

    private function card(Stage $stage): Card
    {
        $this->post($this->url('/cards'), ['parent_id' => $stage->id, 'kind' => 'source', 'byline' => 'El Tío Tech'])->assertRedirect();

        return Card::latest('id')->first();
    }

    private function item(Card $card, string $title = 'Intro'): Item
    {
        $this->post($this->url('/items'), [
            'parent_id' => $card->id,
            'kind' => 'youtube_video',
            'title' => $title,
            'url' => 'https://www.youtube.com/watch?v=abc',
        ])->assertRedirect();

        return Item::latest('id')->first();
    }

    public function test_the_screen_shows_the_latest_versions_tree(): void
    {
        $stage = $this->stage();
        $this->item($this->card($stage));

        $this->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/guides/Content')
                ->where('version.number', 1)
                ->where('version.released', false)
                ->has('stages', 1)
                ->has('stages.0.cards.0.items', 1)
                ->has('kinds.items', 5)
                ->where('linkKinds', ['youtube_video', 'youtube_playlist', 'external_site']));
    }

    public function test_a_lines_replacement_asks_for_the_guides_own_step_length_else_the_hosts(): void
    {
        $this->get($this->url())->assertInertia(fn ($page) => $page->where('stepLength', '4 a 8'));

        $this->guide->forceFill(['prompt_values' => ['topic' => 'Excel', 'step_minutes' => '3 a 5']])->save();

        $this->get($this->url())->assertInertia(fn ($page) => $page->where('stepLength', '3 a 5'));
    }

    public function test_a_tree_is_built_in_order_and_items_get_their_keys(): void
    {
        $first = $this->stage('Etapa 1');
        $second = $this->stage('Etapa 2');
        $card = $this->card($first);
        $item = $this->item($card);

        $this->assertSame([1, 2], [$first->position, $second->position]);
        $this->assertSame($this->guide->latestVersion()->id, $item->version_id);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $item->key);
    }

    public function test_a_section_is_added_to_the_version(): void
    {
        $this->post($this->url('/sections'), ['kind' => 'how_to_use', 'body' => 'Marca cada video al terminarlo.'])
            ->assertRedirect();

        $this->assertSame(['Marca cada video al terminarlo.'], $this->guide->latestVersion()->sections()->pluck('body')->all());
    }

    public function test_a_link_needs_a_url_and_a_note_does_not(): void
    {
        $card = $this->card($this->stage());

        $this->post($this->url('/items'), ['parent_id' => $card->id, 'kind' => 'external_site', 'title' => 'Sitio'])
            ->assertSessionHasErrors('url');
        $this->post($this->url('/items'), ['parent_id' => $card->id, 'kind' => 'youtube_video', 'title' => 'Video', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->post($this->url('/items'), ['parent_id' => $card->id, 'kind' => 'practice', 'title' => 'Haz una tabla', 'body' => 'Con tus gastos.'])
            ->assertSessionHasNoErrors();
    }

    public function test_editing_an_item_keeps_its_key(): void
    {
        $item = $this->item($this->card($this->stage()));

        $this->put($this->url("/items/{$item->id}"), [
            'kind' => 'youtube_video',
            'title' => 'Intro, rewritten',
            'url' => 'https://www.youtube.com/watch?v=xyz',
            'is_preview' => true,
            'is_available' => false,
        ])->assertRedirect();

        $fresh = $item->fresh();
        $this->assertSame([$item->key, 'Intro, rewritten', true, false], [$fresh->key, $fresh->title, $fresh->is_preview, $fresh->is_available]);
    }

    public function test_the_owner_sets_a_clip_as_a_videos_times_with_its_evidence(): void
    {
        $item = $this->item($this->card($this->stage()));
        $edit = fn (array $fields) => $this->put($this->url("/items/{$item->id}"), [
            'kind' => 'youtube_video', 'title' => 'Intro', 'url' => 'https://www.youtube.com/watch?v=abc', ...$fields,
        ]);

        $edit(['clip_start' => '14:00', 'clip_end' => '1:02:49', 'clip_evidence' => 'Chapter "14:00 Fórmulas"'])->assertSessionHasNoErrors();
        $this->assertSame(['clip_start' => 840, 'clip_end' => 3769, 'clip_evidence' => 'Chapter "14:00 Fórmulas"'], $item->fresh()->only(Item::CLIP_FIELDS));

        // No evidence, no clip; an end before the start, or a time that is not one, is refused.
        $edit(['clip_start' => '14:00'])->assertSessionHasErrors('clip_evidence');
        $edit(['clip_start' => '14:00', 'clip_end' => '2:00', 'clip_evidence' => 'x'])->assertSessionHasErrors('clip_end');
        $edit(['clip_start' => 'mañana', 'clip_evidence' => 'x'])->assertSessionHasErrors('clip_start');
        $edit(['clip_end' => '18:00', 'clip_evidence' => 'x'])->assertSessionHasErrors('clip_start');

        // Cleared by leaving it empty.
        $edit(['clip_start' => '', 'clip_end' => '', 'clip_evidence' => ''])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->clip_start);
    }

    public function test_a_new_link_drops_the_old_clip_unless_it_brings_its_own_and_only_a_video_has_one(): void
    {
        $item = $this->item($this->card($this->stage()));
        $item->update(['clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'Chapter']);
        $edit = fn (array $fields) => $this->put($this->url("/items/{$item->id}"), ['kind' => 'youtube_video', 'title' => 'Intro', ...$fields]);

        // The form sends the clip back as it was: on another video, it means nothing.
        $edit(['url' => 'https://www.youtube.com/watch?v=other', 'clip_start' => '14:00', 'clip_end' => '18:00', 'clip_evidence' => 'Chapter'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->clip_start);

        $edit(['url' => 'https://www.youtube.com/watch?v=third', 'clip_start' => '1:00', 'clip_evidence' => 'Transcript at 1:00'])->assertSessionHasNoErrors();
        $this->assertSame(60, $item->fresh()->clip_start);

        $this->put($this->url("/items/{$item->id}"), [
            'kind' => 'external_site', 'title' => 'Un sitio', 'url' => 'https://example.com', 'clip_start' => '1:00', 'clip_evidence' => 'x',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['clip_start' => null, 'clip_end' => null, 'clip_evidence' => null], $item->fresh()->only(Item::CLIP_FIELDS));
    }

    public function test_a_released_lines_clip_is_set_with_fix_everywhere(): void
    {
        $item = $this->item($this->card($this->stage()));
        $this->post("/es/editor/guides/{$this->guide->id}/versions/release");
        $this->post("/es/editor/guides/{$this->guide->id}/versions");

        $this->put("/es/editor/guides/{$this->guide->id}/items/{$item->key}/fix", [
            'title' => 'Intro', 'url' => 'https://www.youtube.com/watch?v=abc', 'is_available' => true,
            'clip_start' => '10:34', 'clip_evidence' => 'Chapter "10:34 Tablas dinámicas"',
        ])->assertSessionHasNoErrors();

        $this->assertSame([634], Item::where('key', $item->key)->pluck('clip_start')->unique()->values()->all());
        $this->assertSame(2, Item::where('key', $item->key)->count());
    }

    public function test_a_node_moves_among_its_siblings_and_stays_at_the_ends(): void
    {
        $card = $this->card($this->stage());
        $a = $this->item($card, 'A');
        $b = $this->item($card, 'B');

        $this->post($this->url("/items/{$b->id}/move/up"))->assertRedirect();
        $this->assertSame(['B', 'A'], $card->items()->pluck('title')->all());

        // Already first: nothing moves.
        $this->post($this->url("/items/{$b->id}/move/up"))->assertRedirect();
        $this->assertSame(['B', 'A'], $card->items()->pluck('title')->all());
        $this->assertSame($a->key, $a->fresh()->key);
    }

    public function test_deleting_a_stage_takes_its_cards_and_items(): void
    {
        $stage = $this->stage();
        $this->item($this->card($stage));

        $this->delete($this->url("/stages/{$stage->id}"))->assertRedirect();

        $this->assertDatabaseCount('guide_cards', 0);
        $this->assertDatabaseCount('guide_items', 0);
    }

    public function test_a_released_version_cannot_be_edited_from_here(): void
    {
        $stage = $this->stage();
        $this->guide->latestVersion()->release();

        $this->post($this->url('/stages'), ['title' => 'Otra'])->assertStatus(409);
        $this->put($this->url("/stages/{$stage->id}"), ['title' => 'Cambiada'])->assertStatus(409);
        $this->assertSame('Etapa 1', $stage->fresh()->title);

        $this->get($this->url())->assertInertia(fn ($page) => $page->where('version.released', true));
    }

    public function test_the_draft_after_a_release_is_what_gets_edited(): void
    {
        $stage = $this->stage();
        $this->guide->latestVersion()->release();
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $copy = $draft->stages()->sole();

        // The released stage is not in the draft: out of reach.
        $this->put($this->url("/stages/{$stage->id}"), ['title' => 'Cambiada'])->assertNotFound();

        $this->put($this->url("/stages/{$copy->id}"), ['title' => 'Etapa 1, v2'])->assertRedirect();
        $this->assertSame(['Etapa 1', 'Etapa 1, v2'], [$stage->fresh()->title, $copy->fresh()->title]);
    }

    public function test_another_guides_rows_are_out_of_reach(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Otra guía', 'level' => Level::BASIC]);
        $otherStage = $other->latestVersion()->stages()->create(['title' => 'Ajena', 'position' => 1]);

        $this->put($this->url("/stages/{$otherStage->id}"), ['title' => 'x'])->assertNotFound();
        $this->delete($this->url("/stages/{$otherStage->id}"))->assertNotFound();
        $this->post($this->url('/cards'), ['parent_id' => $otherStage->id, 'kind' => 'source'])->assertNotFound();

        $this->assertSame(0, $otherStage->cards()->count());
    }

    public function test_another_languages_guide_is_out_of_reach(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel for work', 'level' => Level::BASIC]);

        $this->get("/es/editor/guides/{$english->id}/content")->assertNotFound();
        $this->post("/es/editor/guides/{$english->id}/content/stages", ['title' => 'x'])->assertNotFound();
    }
}
