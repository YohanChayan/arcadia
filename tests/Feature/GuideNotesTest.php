<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Note;
use App\Models\Reader;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reader's own notes on a guide, on a line or a stage: served with the guide page, written,
 * changed and deleted one at a time — theirs alone, on a guide they can open, on a line or stage
 * of the version they read.
 */
class GuideNotesTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Stage $stage;

    private Item $video;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);

        $this->stage = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'goal' => 'Usar fórmulas', 'position' => 1]);
        $card = $this->stage->cards()->create(['kind' => CardKind::SOURCE, 'byline' => 'El Tío Tech', 'position' => 1]);
        $this->video = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Intro', 'body' => 'Mira.', 'position' => 1]);

        $this->guide->latestVersion()->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);
        $this->reader = $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id]);
    }

    private function notesUrl(?Reader $reader = null): string
    {
        return '/es/mis-guias/'.($reader ?? $this->reader)->id.'/notes';
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    public function test_a_reader_writes_a_note_on_a_line_and_on_a_stage_and_finds_them_on_the_page(): void
    {
        $this->actingAs($this->ana);

        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => '  Repasar BUSCARV  '])
            ->assertCreated()
            ->assertJson(['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => 'Repasar BUSCARV']);
        $this->postJson($this->notesUrl(), ['target_kind' => 'stage', 'target_key' => $this->stage->key, 'body' => 'Difícil'])->assertCreated();

        $this->get($this->link())->assertInertia(fn ($page) => $page
            ->has('notes.list', 2)
            ->where('notes.list.0.body', 'Repasar BUSCARV')
            ->where('notes.list.1.target_kind', 'stage')
            ->where('notes.storeUrl', url($this->notesUrl())));
    }

    public function test_a_reader_changes_and_deletes_their_own_note(): void
    {
        $this->actingAs($this->ana);
        $id = $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => 'Uno'])->json('id');

        $this->putJson($this->notesUrl()."/{$id}", ['body' => 'Dos'])->assertOk()->assertJson(['body' => 'Dos']);
        $this->assertSame('Dos', Note::find($id)->body);

        $this->deleteJson($this->notesUrl()."/{$id}")->assertNoContent();
        $this->assertSame(0, Note::count());
    }

    public function test_a_note_is_on_a_line_or_stage_of_the_released_version_and_not_empty(): void
    {
        $this->actingAs($this->ana);

        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => 'nope', 'body' => 'x'])->assertUnprocessable();
        // A stage's key is not a line's.
        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->stage->key, 'body' => 'x'])->assertUnprocessable();
        $this->postJson($this->notesUrl(), ['target_kind' => 'card', 'target_key' => $this->video->key, 'body' => 'x'])->assertUnprocessable();
        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => ''])->assertUnprocessable();
        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => str_repeat('x', Note::MAX_LENGTH + 1)])->assertUnprocessable();

        $this->assertSame(0, Note::count());
    }

    public function test_notes_are_their_writers_alone(): void
    {
        $this->actingAs($this->ana);
        $id = $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => 'Mía'])->json('id');

        $bob = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']);
        $bobsReader = $this->guide->readers()->create(['email' => 'bob@gmail.com', 'user_id' => $bob->id]);
        $this->actingAs($bob);

        // Not on Bob's page, not through Ana's assignment, not by id through his own.
        $this->get($this->link())->assertInertia(fn ($page) => $page->has('notes.list', 0));
        $this->postJson($this->notesUrl(), ['target_kind' => 'item', 'target_key' => $this->video->key, 'body' => 'x'])->assertNotFound();
        $this->putJson($this->notesUrl($bobsReader)."/{$id}", ['body' => 'Hackeada'])->assertNotFound();
        $this->deleteJson($this->notesUrl($bobsReader)."/{$id}")->assertNotFound();

        $this->assertSame('Mía', Note::find($id)->body);
    }

    public function test_the_owner_reading_the_guide_gets_no_notes(): void
    {
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->get($this->link())->assertInertia(fn ($page) => $page->where('notes', null));
    }
}
