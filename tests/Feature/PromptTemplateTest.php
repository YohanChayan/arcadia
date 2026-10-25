<?php

namespace Tests\Feature;

use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptSection;
use App\Models\PromptTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * AI research prompts: a template in versions, each version ordered typed sections. Only the
 * latest version is edited, only while no guide has been produced from it; one template serves
 * both hosts, the language being a variable.
 */
class PromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    private function template(): PromptTemplate
    {
        return PromptTemplate::create(['name' => 'Investigación']);
    }

    private function section(PromptTemplate $template, string $body = 'Eres un curador.', PromptSectionKind $kind = PromptSectionKind::PERSONA)
    {
        return $this->post("/es/editor/prompts/{$template->id}/sections", ['kind' => $kind->value, 'body' => $body]);
    }

    public function test_a_template_is_created_with_its_first_version_and_opened(): void
    {
        $response = $this->post('/es/editor/prompts', ['name' => 'Investigación', 'description' => 'Para Excel']);

        $template = PromptTemplate::sole();
        $response->assertRedirect("/es/editor/prompts/{$template->id}");
        $this->assertSame([1], $template->versions->pluck('number')->all());

        $this->get("/es/editor/prompts/{$template->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/prompts/Show')
                ->where('version.number', 1)
                ->where('version.editable', true)
                ->has('kinds', count(PromptSectionKind::cases())));
    }

    public function test_one_template_serves_both_hosts(): void
    {
        $this->template();

        $this->get('/es/editor/prompts')->assertInertia(fn ($page) => $page->has('templates', 1));
        $this->get('/en/editor/prompts')->assertInertia(fn ($page) => $page->has('templates', 1));
    }

    public function test_sections_are_added_in_order_edited_moved_and_deleted(): void
    {
        $template = $this->template();

        $this->section($template, 'Primero')->assertRedirect();
        $this->section($template, 'Segundo', PromptSectionKind::CONTRACT);

        [$first, $second] = $template->latestVersion()->sections->all();
        $this->assertSame([1, 2], [$first->position, $second->position]);

        $this->put("/es/editor/prompts/{$template->id}/sections/{$first->id}", [
            'kind' => 'brief', 'title' => 'Quién aprende', 'body' => 'Quiere aprender {{topic}}',
        ])->assertRedirect();
        $this->assertSame('Quiere aprender {{topic}}', $first->fresh()->body);

        $this->post("/es/editor/prompts/{$template->id}/sections/{$second->id}/move/up");
        $this->assertSame(['Segundo', 'Quiere aprender {{topic}}'], $template->latestVersion()->sections()->pluck('body')->all());

        $this->delete("/es/editor/prompts/{$template->id}/sections/{$first->id}")->assertRedirect();
        $this->assertSame(1, PromptSection::count());
    }

    public function test_a_section_needs_a_kind_and_a_text(): void
    {
        $template = $this->template();

        $this->post("/es/editor/prompts/{$template->id}/sections", ['kind' => 'poem', 'body' => ''])
            ->assertSessionHasErrors(['kind', 'body']);
    }

    public function test_a_new_version_copies_the_latest_and_the_earlier_one_becomes_history(): void
    {
        $template = $this->template();
        $this->section($template, 'Primero');
        $original = PromptSection::sole();

        $this->post("/es/editor/prompts/{$template->id}/versions")
            ->assertRedirect("/es/editor/prompts/{$template->id}?version=2");

        $latest = $template->latestVersion();
        $this->assertSame(2, $latest->number);
        $this->assertSame(['Primero'], $latest->sections->pluck('body')->all());

        // Version 1 opens read-only, and its sections are not the ones being edited.
        $this->get("/es/editor/prompts/{$template->id}?version=1")
            ->assertInertia(fn ($page) => $page->where('version.editable', false)->where('version.latest', false));
        $this->delete("/es/editor/prompts/{$template->id}/sections/{$original->id}")->assertNotFound();
    }

    public function test_a_frozen_version_cannot_change(): void
    {
        $template = $this->template();
        $this->section($template, 'Primero');
        $section = PromptSection::sole();
        $template->latestVersion()->freeze();

        $this->section($template, 'Otro')->assertStatus(409);
        $this->put("/es/editor/prompts/{$template->id}/sections/{$section->id}", ['kind' => 'persona', 'body' => 'x'])->assertStatus(409);
        $this->delete("/es/editor/prompts/{$template->id}/sections/{$section->id}")->assertStatus(409);
        $this->delete("/es/editor/prompts/{$template->id}")->assertStatus(409);

        $this->expectException(LogicException::class);
        $section->update(['body' => 'por la puerta de atrás']);
    }

    public function test_a_frozen_version_can_still_be_carried_on_in_a_new_one(): void
    {
        $template = $this->template();
        $this->section($template, 'Primero');
        $template->latestVersion()->freeze();

        $this->post("/es/editor/prompts/{$template->id}/versions");
        $this->section($template, 'Segundo')->assertRedirect();

        $this->assertSame(['Primero', 'Segundo'], $template->latestVersion()->sections()->pluck('body')->all());
    }

    public function test_a_version_is_described_even_once_frozen_and_a_new_one_starts_without(): void
    {
        $template = $this->template();
        $v1 = $template->latestVersion();
        $v1->freeze();

        $this->put("/es/editor/prompts/{$template->id}/versions/{$v1->id}", ['description' => 'Con foto de referencia'])
            ->assertRedirect();
        $this->assertSame('Con foto de referencia', $v1->fresh()->description);

        $this->put("/es/editor/prompts/{$template->id}/versions/{$v1->id}", ['description' => str_repeat('x', 256)])
            ->assertSessionHasErrors('description');

        $this->post("/es/editor/prompts/{$template->id}/versions");
        $this->assertNull($template->latestVersion()->description);
        $this->get("/es/editor/prompts/{$template->id}?version=1")
            ->assertInertia(fn ($page) => $page->where('version.description', 'Con foto de referencia'));
    }

    public function test_a_version_of_another_template_is_not_described_through_this_one(): void
    {
        $template = $this->template();
        $other = PromptTemplate::create(['name' => 'Otro']);

        $this->put("/es/editor/prompts/{$template->id}/versions/{$other->latestVersion()->id}", ['description' => 'x'])
            ->assertNotFound();
    }

    public function test_a_template_with_no_guide_behind_it_is_deleted_with_its_versions(): void
    {
        $template = $this->template();
        $this->section($template);

        $this->delete("/es/editor/prompts/{$template->id}")->assertRedirect('/es/editor/prompts');

        $this->assertSame(0, PromptTemplate::count());
        $this->assertSame(0, PromptSection::count());
    }

    public function test_the_starter_template_is_seeded_once_with_its_contract_last(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $sections = PromptTemplate::sole()->latestVersion()->sections;
        $this->assertCount(count(PromptSectionKind::cases()), $sections);
        $this->assertSame(PromptSectionKind::CONTRACT, $sections->last()->kind);
        $this->assertStringContainsString('{{language}}', $sections->first()->body);
    }

    public function test_a_fresh_seed_is_one_version_that_already_asks_for_the_brief(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $template = PromptTemplate::sole();
        $this->assertSame(1, $template->versions()->count());
        $this->assertStringContainsString('"brief"', $template->latestVersion()->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->body);
    }

    public function test_a_template_seeded_before_the_brief_gets_one_new_version_with_it(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $template = PromptTemplate::sole();
        $v1 = $template->latestVersion();

        // As production holds it: seeded before 2026-10-16, the owner's own wording in one section, used once.
        $v1->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->update(['body' => 'Responde con JSON.']);
        $v1->sections()->where('kind', PromptSectionKind::RESEARCH)->sole()->update(['body' => 'Busca en YouTube.']);
        $v1->sections()->where('kind', PromptSectionKind::PERSONA)->sole()->update(['body' => 'Mi propio texto']);
        $v1->freeze();

        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $this->assertSame(2, $template->versions()->count());
        $v2 = $template->latestVersion();
        $this->assertSame(2, $v2->number);
        $this->assertStringContainsString('"brief"', $v2->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->body);
        $this->assertStringContainsString('brief', $v2->sections()->where('kind', PromptSectionKind::RESEARCH)->sole()->body);
        // Only those two: the owner's own wording elsewhere is copied as it was.
        $this->assertSame('Mi propio texto', $v2->sections()->where('kind', PromptSectionKind::PERSONA)->sole()->body);
        $this->assertSame($v1->variables()->count(), $v2->variables()->count());
        // And version 1 keeps its text.
        $this->assertSame('Responde con JSON.', $v1->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->body);
    }

    public function test_an_unused_latest_version_is_brought_up_to_date_in_place_greeting_included(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $template = PromptTemplate::sole();
        $v1 = $template->latestVersion();
        // Seeded before the greeting wrote {name}, and never used.
        $v1->sections()->where('kind', PromptSectionKind::COPY)->sole()->update(['body' => 'greeting: un saludo para {{learner_name}}.']);

        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $this->assertSame(1, $template->versions()->count());
        $this->assertStringContainsString('{name}', $v1->sections()->where('kind', PromptSectionKind::COPY)->sole()->body);
    }

    public function test_a_used_template_gets_one_new_version_asking_for_short_steps_and_evidenced_clips(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $template = PromptTemplate::sole();
        $v1 = $template->latestVersion();
        $section = fn ($version, PromptSectionKind $kind) => $version->sections()->where('kind', $kind)->sole()->body;

        // As it stood before 2026-10-20: up to five stages, no clips, and used once.
        $v1->sections()->where('kind', PromptSectionKind::STRUCTURE)->sole()->update(['body' => 'Organiza la ruta en 3 a 5 etapas.']);
        $v1->sections()->where('kind', PromptSectionKind::RESEARCH)->sole()->update(['body' => 'Busca en YouTube. Escribe su ficha («brief»).']);
        $v1->sections()->where('kind', PromptSectionKind::CONTRACT)->sole()->update(['body' => 'Responde con JSON, con "brief".']);
        $v1->freeze();

        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $this->assertSame(2, $template->versions()->count());
        $v2 = $template->latestVersion();
        $this->assertStringContainsString('4 a 8 etapas', $section($v2, PromptSectionKind::STRUCTURE));
        $this->assertStringContainsString('de {{step_minutes}} minutos', $section($v2, PromptSectionKind::RESEARCH));
        $this->assertSame(PromptVariableType::STEP_LENGTH, $v2->variables()->where('name', 'step_minutes')->sole()->type);
        $this->assertStringContainsString('"evidence"', $section($v2, PromptSectionKind::CONTRACT));
        $this->assertSame('Organiza la ruta en 3 a 5 etapas.', $section($v1, PromptSectionKind::STRUCTURE));
    }

    public function test_only_the_owner_edits_prompts(): void
    {
        $this->actingAs(User::factory()->create(['approved_at' => now(), 'email_verified_at' => now()]));

        $this->get('/es/editor/prompts')->assertForbidden();
        $this->post('/es/editor/prompts', ['name' => 'x'])->assertForbidden();
    }
}
