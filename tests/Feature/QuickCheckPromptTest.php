<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\PromptSectionKind;
use App\Models\Guide;
use App\Models\Item;
use App\Models\PromptTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityImport;
use App\Services\GuideImport;
use App\Services\PromptRenderer;
use Database\Seeders\QuickCheckPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quick-check prompt, opened on a guide: its variables filled from the guide, and its videos —
 * the ones a question can rest on — put in by the server, never through the form. Its contract is
 * the shape "Importar actividades" takes back.
 */
class QuickCheckPromptTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]));
        $this->seed(QuickCheckPromptTemplateSeeder::class);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Fórmulas', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);

        $this->video = $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Suma', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 1, 'clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'x']);
        // Braces in a guide's own text are text, never a placeholder.
        $this->video->forceFill(['brief' => ['summary' => 'Fórmulas con = y {{level}}', 'covers' => ['SUMA']]])->save();
        $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Sin resumen', 'url' => 'https://youtu.be/abcDEF12345', 'position' => 2]);
        $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Tu suma', 'body' => 'x', 'position' => 3]);
    }

    private function fill(array $extra = [])
    {
        $template = PromptTemplate::query()->where('name', PromptTemplate::QUICK_CHECK)->sole();

        return $this->postJson("/es/editor/prompts/{$template->id}/fill", [
            'version_id' => $template->latestVersion()->id,
            'values' => ['title' => 'Excel', 'level' => 'basic', 'language' => 'es', 'questions' => '1 o 2'],
            ...$extra,
        ]);
    }

    public function test_opened_on_a_guide_it_carries_the_videos_a_question_can_rest_on(): void
    {
        $prompt = $this->fill(['guide_id' => $this->guide->id])->assertOk()->assertJsonPath('missing', [])->json('prompt');

        $this->assertStringContainsString('"key": "'.$this->video->key.'"', $prompt);
        $this->assertStringContainsString('"stage": "1 · Fórmulas"', $prompt);
        $this->assertStringContainsString('"clip": "14:00–18:00"', $prompt);
        $this->assertStringContainsString('Fórmulas con = y {{level}}', $prompt);
        // A video with no brief, and anything but a video, is left out.
        $this->assertStringNotContainsString('Sin resumen', $prompt);
        $this->assertStringNotContainsString('Tu suma', $prompt);
        $this->assertStringContainsString('guía «Excel» (nivel Básico)', $prompt);
    }

    public function test_opened_anywhere_else_it_says_where_the_content_will_go(): void
    {
        $this->fill()->assertOk()
            ->assertJsonPath('undeclared', [])
            ->assertJson(fn ($json) => $json->where('prompt', fn ($prompt) => str_contains($prompt, __('arcadia.editor.prompts.fill.content_elsewhere')))->etc());

        // Only this editor's own guides.
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel', 'level' => Level::BASIC]);
        $this->fill(['guide_id' => $english->id])->assertNotFound();
    }

    public function test_no_variable_may_take_the_contents_name(): void
    {
        $template = PromptTemplate::query()->where('name', PromptTemplate::QUICK_CHECK)->sole();
        $template->update(['name' => 'Copia']);
        $template->latestVersion()->update(['frozen_at' => null]);

        $this->post("/es/editor/prompts/{$template->id}/variables", ['name' => PromptRenderer::CONTENT, 'label' => 'x', 'type' => 'text'])
            ->assertSessionHasErrors('name');
    }

    public function test_the_content_screen_offers_it_filled_from_the_guide(): void
    {
        $this->get("/es/editor/guides/{$this->guide->id}/content")->assertInertia(fn ($page) => $page
            ->where('activityPrompts.0.family', 'quick_check')
            ->where('activityPrompts.0.prompt.name', PromptTemplate::QUICK_CHECK)
            ->where('activityPrompts.0.values', ['title' => 'Excel', 'level' => 'basic', 'language' => 'es']));
    }

    public function test_its_contract_is_the_shape_the_import_takes_back(): void
    {
        $contract = PromptTemplate::query()->where('name', PromptTemplate::QUICK_CHECK)->sole()
            ->latestVersion()->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->body;

        // The example in the contract, with a real key, passes the import's own check.
        preg_match('/```json\n(.*?)```/s', $contract, $fence);
        $example = app(GuideImport::class)->extract($fence[1]);
        $example['activities'][0]['key'] = $this->video->key;

        $this->assertSame([], app(ActivityImport::class)->problems($example, $this->guide->latestVersion()));

        // Run again, the seeder writes nothing.
        $this->seed(QuickCheckPromptTemplateSeeder::class);
        $this->assertSame(1, PromptTemplate::query()->where('name', PromptTemplate::QUICK_CHECK)->count());
    }
}
