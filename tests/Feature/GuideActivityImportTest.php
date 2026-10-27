<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\SectionKind;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Role;
use App\Models\Section;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * An AI's answer to an activity prompt, attached to a draft's nodes by their keys: checked first,
 * at each problem's place, with nothing written; then attached without touching the nodes
 * themselves, replacing only its own family. And the owner's review: reworded, or removed.
 */
class GuideActivityImportTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Section $mustMaster;

    private Stage $stage;

    private Item $video;

    private Item $practice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]));

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $draft = $this->guide->latestVersion();
        $this->mustMaster = $draft->sections()->create(['kind' => SectionKind::MUST_MASTER, 'body' => '1. Fórmulas', 'position' => 1]);
        $this->stage = $draft->stages()->create(['title' => 'Fórmulas', 'goal' => 'Sumar', 'position' => 1]);
        $card = $this->stage->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->video = $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Suma', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'hint' => 'Video · 6 min', 'position' => 1]);
        $this->video->forceFill(['brief' => ['summary' => 'Fórmulas con =']])->save();
        $this->practice = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu suma', 'body' => 'Suma tus gastos.', 'position' => 2]);
    }

    /** @param  array<string, mixed>  $answer */
    private function file(array $answer): UploadedFile
    {
        // As an AI answers: a sentence, then the JSON in a fence.
        return UploadedFile::fake()->createWithContent('respuesta.json', "Aquí está:\n```json\n".json_encode($answer)."\n```");
    }

    /** @return array<string, mixed> */
    private function quickCheck(?string $key = null, int $correct = 1): array
    {
        return ['family' => 'quick_check', 'activities' => [[
            'key' => $key ?? $this->video->key,
            'questions' => [['prompt' => '¿Con qué empieza?', 'options' => ['#', '='], 'correct' => $correct, 'explanation' => 'Con =.', 'evidence' => 'Fórmulas con =']],
        ]]];
    }

    private function preview(array $answer)
    {
        return $this->postJson("/es/editor/guides/{$this->guide->id}/activities/import/preview", ['file' => $this->file($answer)]);
    }

    private function import(array $answer)
    {
        return $this->post("/es/editor/guides/{$this->guide->id}/activities/import", ['file' => $this->file($answer)]);
    }

    public function test_the_preview_says_what_will_be_attached_and_writes_nothing(): void
    {
        $this->preview($this->quickCheck())->assertOk()->assertJson([
            'problems' => [],
            'summary' => ['family' => 'quick_check', 'nodes' => 1, 'entries' => 1, 'replaces' => 0],
        ]);

        $this->assertNull($this->video->fresh()->activity);
    }

    public function test_every_problem_is_named_at_its_place_and_nothing_is_written(): void
    {
        $answer = ['family' => 'quick_check', 'activities' => [
            // A key this draft does not have.
            ['key' => 'nonexistent', 'questions' => [['prompt' => 'a', 'options' => ['x', 'y'], 'correct' => 0, 'explanation' => 'e', 'evidence' => 'v']]],
            // A quick check on a practice line, which only a video takes.
            ['key' => $this->practice->key, 'questions' => [['prompt' => 'a', 'options' => ['x', 'y'], 'correct' => 0, 'explanation' => 'e', 'evidence' => 'v']]],
            // A correct option it does not have, and no explanation.
            ['key' => $this->video->key, 'questions' => [['prompt' => 'a', 'options' => ['x', 'y'], 'correct' => 2, 'evidence' => 'v']]],
            ['key' => $this->video->key, 'questions' => [['prompt' => 'a', 'options' => ['x', 'y'], 'correct' => 0, 'explanation' => 'e', 'evidence' => 'v']]],
        ]];

        $places = collect($this->preview($answer)->assertOk()->json('problems'))->pluck('place');

        $this->assertEqualsCanonicalizing([
            'Actividad 1 · key',
            'Actividad 2 · key',
            'Actividad 3 · pregunta 1 · explanation',
            'Actividad 3 · pregunta 1 · correct',
            'Actividad 4 · key',
        ], $places->all());

        $this->import($answer)->assertSessionHasErrors('file');
        $this->assertNull($this->video->fresh()->activity);

        $this->preview(['activities' => []])->assertJsonPath('problems.0.place', 'family');
        $this->preview(['family' => 'stage_game', 'activities' => [['key' => $this->stage->key, 'game' => 'blanks', 'pairs' => [
            ['left' => 'Una = abre la fórmula', 'right' => 'igual'], ['left' => '___ suma', 'right' => 'SUMA'], ['left' => '___ promedia', 'right' => 'PROMEDIO'],
        ]]]])->assertJsonPath('problems.0.place', 'Actividad 1 · par 1 · left');
    }

    public function test_attaching_changes_only_the_activity_of_each_node(): void
    {
        $before = $this->video->fresh()->only('key', 'title', 'url', 'hint', 'body', 'brief');

        $this->import($this->quickCheck())->assertSessionHasNoErrors();

        $video = $this->video->fresh();
        $this->assertSame($before, $video->only('key', 'title', 'url', 'hint', 'body', 'brief'));
        $this->assertSame('quick_check', $video->activity['family']);
        $this->assertSame(1, $video->activity['questions'][0]['correct']);
        $this->assertSame('Fórmulas con =', $video->activity['questions'][0]['evidence']);
    }

    public function test_importing_a_family_again_replaces_that_family_and_no_other(): void
    {
        $this->import($this->quickCheck())->assertSessionHasNoErrors();
        $this->import(['family' => 'practice_extras', 'activities' => [['key' => $this->practice->key, 'hint' => 'Empieza con =', 'example' => '=SUMA(A1:A3)']]])->assertSessionHasNoErrors();
        $this->assertTrue($this->practice->fresh()->activity['rate']);

        // A new answer that leaves the video out: its old quick check goes, the practice's extras stay.
        $other = $this->stage->cards()->sole()->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Resta', 'url' => 'https://youtu.be/abcDEF12345', 'position' => 3]);
        $this->preview($this->quickCheck($other->key))->assertJsonPath('summary.replaces', 1);
        $this->import($this->quickCheck($other->key))->assertSessionHasNoErrors();

        $this->assertNull($this->video->fresh()->activity);
        $this->assertSame('quick_check', $other->fresh()->activity['family']);
        $this->assertSame('practice_extras', $this->practice->fresh()->activity['family']);
    }

    public function test_flip_cards_are_given_their_own_keys(): void
    {
        $cards = array_map(fn ($n) => ['front' => "Frente {$n}", 'back' => "Dorso {$n}", 'key' => 'mine'], [1, 2, 3]);

        $this->import(['family' => 'flip_cards', 'activities' => [['key' => $this->mustMaster->key, 'cards' => $cards]]])->assertSessionHasNoErrors();

        $keys = collect($this->mustMaster->fresh()->activity['cards'])->pluck('key');
        $this->assertCount(3, $keys->unique());
        $this->assertNotContains('mine', $keys);
    }

    public function test_a_released_version_takes_no_activities_and_no_review(): void
    {
        $this->import($this->quickCheck())->assertSessionHasNoErrors();
        $this->guide->latestVersion()->release();

        $this->preview($this->quickCheck())->assertStatus(409);
        $this->import($this->quickCheck())->assertStatus(409);
        $this->put("/es/editor/guides/{$this->guide->id}/activities/items/{$this->video->id}", ['activity' => []])->assertStatus(409);
        $this->delete("/es/editor/guides/{$this->guide->id}/activities/items/{$this->video->id}")->assertStatus(409);
    }

    public function test_the_owner_rewords_an_activity_and_nothing_else_of_it(): void
    {
        $this->import($this->quickCheck())->assertSessionHasNoErrors();
        $url = "/es/editor/guides/{$this->guide->id}/activities/items/{$this->video->id}";

        $this->put($url, ['activity' => [
            'questions' => [['prompt' => '¿Con qué símbolo empieza una fórmula?', 'options' => ['#', 'Un ='], 'correct' => 0, 'explanation' => 'Siempre con =.', 'evidence' => 'otra']],
            // Not wording: an extra question is not taken.
            'family' => 'stage_game',
        ]])->assertSessionHasNoErrors();

        $question = $this->video->fresh()->activity['questions'];
        $this->assertSame([[
            'prompt' => '¿Con qué símbolo empieza una fórmula?',
            'options' => ['#', 'Un ='],
            'correct' => 1,
            'explanation' => 'Siempre con =.',
            'evidence' => 'Fórmulas con =',
        ]], $question);
        $this->assertSame('quick_check', $this->video->fresh()->activity['family']);

        // Wording cannot be emptied.
        $this->put($url, ['activity' => ['questions' => [['prompt' => '']]]])->assertSessionHasErrors('questions.0.prompt');
    }

    public function test_the_owner_removes_an_activity(): void
    {
        $this->import($this->quickCheck())->assertSessionHasNoErrors();

        $this->delete("/es/editor/guides/{$this->guide->id}/activities/items/{$this->video->id}")->assertRedirect();

        $this->assertNull($this->video->fresh()->activity);
        $this->assertSame('Suma', $this->video->fresh()->title);
    }
}
