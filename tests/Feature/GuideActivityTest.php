<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
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
use App\Services\VersionDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * A node's activity (`ActivityFamily`): only what the page may show reaches it — never evidence,
 * and never a quick check's answer to a reader — and it travels with its node from version to
 * version, frozen with a release and seen by Compare.
 */
class GuideActivityTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Section $mustMaster;

    private Stage $stage;

    private Item $video;

    private Item $practice;

    private const QUICK_CHECK = [
        'family' => 'quick_check',
        'questions' => [[
            'prompt' => '¿Con qué empieza una fórmula?',
            'options' => ['=', '#', '@'],
            'correct' => 0,
            'explanation' => 'Toda fórmula empieza con =.',
            'evidence' => 'Explica que las fórmulas empiezan con =',
        ]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $version = $this->guide->latestVersion();

        $this->mustMaster = $version->sections()->create(['kind' => SectionKind::MUST_MASTER, 'body' => '1. Fórmulas', 'position' => 1]);
        $this->stage = $version->stages()->create(['title' => 'Fórmulas', 'goal' => 'Sumar', 'position' => 1]);
        $card = $this->stage->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->video = $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Suma', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 1]);
        $this->practice = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu suma', 'body' => 'x', 'position' => 2]);

        $this->video->forceFill(['activity' => self::QUICK_CHECK, 'brief' => ['summary' => 'Fórmulas']])->save();
        $this->practice->forceFill(['activity' => ['family' => 'practice_extras', 'hint' => 'Empieza con =', 'example' => '=SUMA(A1:A3)', 'rate' => true, 'evidence' => 'x']])->save();
        $this->stage->forceFill(['activity' => ['family' => 'stage_game', 'game' => 'pairs', 'instructions' => 'Une', 'pairs' => [['left' => 'SUMA', 'right' => 'Suma', 'evidence' => 'x']], 'evidence' => 'x']])->save();
        $this->mustMaster->forceFill(['activity' => ['family' => 'flip_cards', 'cards' => [['key' => 'c1', 'front' => '¿=?', 'back' => 'Fórmula', 'evidence' => 'x']]]])->save();

        $version->release();
    }

    private function asReader(): static
    {
        $user = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->guide->readers()->create(['email' => $user->email, 'user_id' => $user->id]);

        return $this->actingAs($user);
    }

    private function asOwner(): static
    {
        return $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]));
    }

    public function test_a_reader_gets_no_answer_explanation_brief_or_evidence(): void
    {
        $this->asReader()->get("/guia/{$this->guide->link_token}")->assertInertia(fn ($page) => $page
            ->where('stages.0.cards.0.items.0.activity', ['family' => 'quick_check', 'questions' => [['prompt' => '¿Con qué empieza una fórmula?', 'options' => ['=', '#', '@']]]])
            ->missing('stages.0.cards.0.items.0.brief')
            ->where('stages.0.cards.0.items.1.activity', ['family' => 'practice_extras', 'hint' => 'Empieza con =', 'example' => '=SUMA(A1:A3)', 'rate' => true])
            ->where('stages.0.activity', ['family' => 'stage_game', 'game' => 'pairs', 'instructions' => 'Une', 'pairs' => [['left' => 'SUMA', 'right' => 'Suma']], 'steps' => []])
            ->where('sections.0.activity', ['family' => 'flip_cards', 'cards' => [['key' => 'c1', 'front' => '¿=?', 'back' => 'Fórmula']]]));
    }

    public function test_the_owner_and_the_preview_get_the_answers_and_never_the_evidence(): void
    {
        $withAnswers = ['family' => 'quick_check', 'questions' => [['prompt' => '¿Con qué empieza una fórmula?', 'options' => ['=', '#', '@'], 'correct' => 0, 'explanation' => 'Toda fórmula empieza con =.']]];

        $this->asOwner()->get("/guia/{$this->guide->link_token}")
            ->assertInertia(fn ($page) => $page->where('stages.0.cards.0.items.0.activity', $withAnswers));

        $this->get("/es/editor/guides/{$this->guide->id}/preview")
            ->assertInertia(fn ($page) => $page->where('stages.0.cards.0.items.0.activity', $withAnswers));
    }

    public function test_an_unknown_family_or_one_on_the_wrong_node_shows_nothing(): void
    {
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $video = $draft->items()->where('kind', ItemKind::YOUTUBE_VIDEO)->sole();
        $practice = $draft->items()->where('kind', ItemKind::PRACTICE)->sole();

        $video->forceFill(['activity' => ['family' => 'riddle', 'answer' => 'x']])->save();
        $practice->forceFill(['activity' => self::QUICK_CHECK])->save();
        $draft->release();

        $this->asReader()->get("/guia/{$this->guide->link_token}")->assertInertia(fn ($page) => $page
            ->where('stages.0.cards.0.items.0.activity', null)
            ->where('stages.0.cards.0.items.1.activity', null));
    }

    public function test_a_new_version_carries_every_activity_and_a_released_one_refuses_a_change(): void
    {
        $draft = app(DraftNextVersion::class)->handle($this->guide);

        $this->assertSame(self::QUICK_CHECK, $draft->items()->where('kind', ItemKind::YOUTUBE_VIDEO)->sole()->activity);
        $this->assertSame('stage_game', $draft->stages()->sole()->activity['family']);
        $this->assertSame('c1', $draft->sections()->sole()->activity['cards'][0]['key']);

        $this->expectException(LogicException::class);
        $this->video->fresh()->forceFill(['activity' => null])->save();
    }

    public function test_compare_shows_an_activity_that_changed(): void
    {
        $released = $this->guide->latestVersion();
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $draft->items()->where('kind', ItemKind::YOUTUBE_VIDEO)->sole()
            ->forceFill(['activity' => [...self::QUICK_CHECK, 'questions' => []]])->save();

        $diff = app(VersionDiff::class)->between($released, $draft);
        $video = collect($diff['stages'][0]['children'][0]['children'])->firstWhere('node.title', 'Suma');

        $this->assertSame('changed', $video['status']);
        $this->assertSame(self::QUICK_CHECK, $video['changed']['activity']['from']);
        $this->assertSame(1, $diff['summary']['items']['changed']);
    }
}
