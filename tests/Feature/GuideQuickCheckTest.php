<?php

namespace Tests\Feature;

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
 * A video's quick check, answered by its reader: checked on the server, a right answer ticks the
 * line and a wrong one says why — and a line that asks is ticked no other way.
 */
class GuideQuickCheckTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $video;

    private Item $practice;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Fórmulas', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->video = $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Suma', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 1]);
        $this->video->forceFill(['activity' => ['family' => 'quick_check', 'questions' => [
            ['prompt' => '¿Con qué empieza?', 'options' => ['#', '='], 'correct' => 1, 'explanation' => 'Con =.', 'evidence' => 'x'],
            ['prompt' => '¿Qué suma?', 'options' => ['SUMA', 'RESTA', 'MAX'], 'correct' => 0, 'explanation' => 'SUMA suma.', 'evidence' => 'x'],
        ]]])->save();
        $this->practice = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu suma', 'body' => 'x', 'position' => 2]);
        $this->guide->latestVersion()->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['approved_at' => null]);
        $this->reader = $this->guide->readers()->create(['email' => $this->ana->email, 'user_id' => $this->ana->id]);
    }

    private function answer(array $answers, ?string $key = null, ?Reader $reader = null)
    {
        return $this->actingAs($this->ana)->putJson('/es/mis-guias/'.($reader ?? $this->reader)->id.'/check', ['key' => $key ?? $this->video->key, 'answers' => $answers]);
    }

    public function test_the_page_says_where_to_answer_and_carries_no_answer(): void
    {
        $this->actingAs($this->ana)->get("/guia/{$this->guide->link_token}")->assertInertia(fn ($page) => $page
            ->where('progress.checkUrl', url("/es/mis-guias/{$this->reader->id}/check"))
            ->missing('stages.0.cards.0.items.0.activity.questions.0.correct')
            ->missing('stages.0.cards.0.items.0.activity.questions.0.explanation'));
    }

    public function test_a_right_answer_ticks_the_line(): void
    {
        $this->answer([1, 0])->assertOk()->assertExactJson(['passed' => true, 'results' => [['correct' => true], ['correct' => true]]]);

        $this->assertSame([$this->video->key], $this->reader->tickedKeys());
    }

    public function test_a_wrong_answer_says_why_and_ticks_nothing(): void
    {
        $this->answer([0, 0])->assertOk()->assertExactJson(['passed' => false, 'results' => [['correct' => false, 'explanation' => 'Con =.'], ['correct' => true]]]);

        $this->assertSame(0, Tick::count());

        // An answer for every question, each an option number.
        $this->answer([1])->assertUnprocessable();
        $this->answer([1, 'x'])->assertUnprocessable();
    }

    public function test_a_line_that_asks_is_ticked_only_by_answering_it(): void
    {
        $tick = fn (Item $item, bool $done) => $this->actingAs($this->ana)->putJson("/es/mis-guias/{$this->reader->id}/progress", ['key' => $item->key, 'done' => $done]);

        $tick($this->video, true)->assertUnprocessable();
        $this->assertSame(0, Tick::count());

        // Any other line ticks as before, and an answered one can be cleared.
        $tick($this->practice, true)->assertNoContent();
        $this->answer([1, 0])->assertOk();
        $tick($this->video, false)->assertNoContent();
        $this->assertSame([$this->practice->key], $this->reader->tickedKeys());
    }

    public function test_only_their_own_assignment_and_a_line_that_asks(): void
    {
        $other = User::factory()->withRole(Role::ALIAS_READER)->create();
        $theirs = $this->guide->readers()->create(['email' => $other->email, 'user_id' => $other->id]);

        $this->answer([1, 0], reader: $theirs)->assertNotFound();
        $this->answer([0], key: $this->practice->key)->assertNotFound();
        $this->answer([0], key: 'nonexistent')->assertNotFound();
        $this->assertSame(0, Tick::count());
    }
}
