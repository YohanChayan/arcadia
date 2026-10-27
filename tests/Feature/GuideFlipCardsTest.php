<?php

namespace Tests\Feature;

use App\Actions\DraftNextVersion;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Enums\PromptSectionKind;
use App\Enums\SectionKind;
use App\Models\ActivityState;
use App\Models\Guide;
use App\Models\PromptTemplate;
use App\Models\Reader;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\ActivityImport;
use App\Services\GuideImport;
use Database\Seeders\FlipCardsPromptTemplateSeeder;
use Database\Seeders\QuickCheckPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "must master" list as flip cards: a reader's "knew it" or "not yet" kept on their account by
 * each card's own key, and brought back with the page; and the prompt that writes the deck, filled
 * from the guide's list, in the shape the import takes back.
 */
class GuideFlipCardsTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Section $mustMaster;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $version = $this->guide->latestVersion();
        $this->mustMaster = $version->sections()->create(['kind' => SectionKind::MUST_MASTER, 'body' => "1. Fórmulas con =\n2. El signo \$ (F4)", 'position' => 1]);
        $this->mustMaster->forceFill(['activity' => ['family' => 'flip_cards', 'cards' => [
            ['key' => 'carda', 'front' => '¿Con qué empieza una fórmula?', 'back' => 'Con =', 'evidence' => 'x'],
            ['key' => 'cardb', 'front' => '¿Qué hace F4?', 'back' => 'Fija la celda con $'],
            ['key' => 'cardc', 'front' => 'Copias una fórmula y se daña', 'back' => 'Fija la celda con $'],
        ]]])->save();
        $version->stages()->create(['title' => 'Fórmulas', 'goal' => 'Sumar una lista', 'position' => 1]);
        $version->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['approved_at' => null]);
        $this->reader = $this->guide->readers()->create(['email' => $this->ana->email, 'user_id' => $this->ana->id]);
    }

    private function answer(string $key, bool $known, ?Reader $reader = null)
    {
        return $this->actingAs($this->ana)->putJson('/es/mis-guias/'.($reader ?? $this->reader)->id.'/cards', ['key' => $key, 'known' => $known]);
    }

    public function test_a_readers_answer_is_kept_by_the_cards_key_and_comes_back_with_the_page(): void
    {
        $this->answer('carda', true)->assertNoContent();
        $this->answer('cardb', false)->assertNoContent();
        // Answering again replaces it.
        $this->answer('cardb', true)->assertNoContent();

        $this->assertSame(2, ActivityState::count());

        $this->actingAs($this->ana)->get("/guia/{$this->guide->link_token}")->assertInertia(fn ($page) => $page
            ->where('cards.states.carda.state', 'known')
            ->where('cards.states.cardb.state', 'known')
            ->where('cards.saveUrl', url("/es/mis-guias/{$this->reader->id}/cards"))
            ->where('sections.0.activity.cards.0', ['key' => 'carda', 'front' => '¿Con qué empieza una fórmula?', 'back' => 'Con =']));
    }

    public function test_only_their_own_assignment_and_a_card_of_the_deck(): void
    {
        $other = User::factory()->withRole(Role::ALIAS_READER)->create();
        $theirs = $this->guide->readers()->create(['email' => $other->email, 'user_id' => $other->id]);

        $this->answer('carda', true, $theirs)->assertNotFound();
        $this->answer('nonexistent', true)->assertNotFound();
        $this->assertSame(0, ActivityState::count());

        // The owner reading as-is plays it for the visit: nothing to save to.
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]))
            ->get("/guia/{$this->guide->link_token}")->assertInertia(fn ($page) => $page->where('cards', null));
    }

    public function test_the_prompt_is_filled_with_the_list_and_answers_in_the_imports_shape(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]));
        $this->seed([QuickCheckPromptTemplateSeeder::class, FlipCardsPromptTemplateSeeder::class]);
        $template = PromptTemplate::query()->where('name', PromptTemplate::FLIP_CARDS)->sole();

        $prompt = $this->postJson("/es/editor/prompts/{$template->id}/fill", [
            'version_id' => $template->latestVersion()->id,
            'values' => ['title' => 'Excel', 'level' => 'basic', 'language' => 'es', 'cards' => '12'],
            'guide_id' => $this->guide->id,
        ])->assertOk()->assertJsonPath('missing', [])->json('prompt');

        $this->assertStringContainsString('"key": "'.$this->mustMaster->key.'"', $prompt);
        $this->assertStringContainsString('El signo $ (F4)', $prompt);
        $this->assertStringContainsString('"goal": "Sumar una lista"', $prompt);

        // Its example, given the list's key, passes the import's own check.
        $contract = $template->latestVersion()->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->body;
        preg_match('/```json\n(.*?)```/s', $contract, $fence);
        $example = app(GuideImport::class)->extract($fence[1]);
        $example['activities'][0]['key'] = $this->mustMaster->key;
        $draft = app(DraftNextVersion::class)->handle($this->guide);
        $this->assertSame([], app(ActivityImport::class)->problems($example, $draft));

        // The content screen offers it beside the quick checks'.
        $this->get("/es/editor/guides/{$this->guide->id}/content")->assertInertia(fn ($page) => $page
            ->where('activityPrompts.1.family', 'flip_cards'));
    }
}
