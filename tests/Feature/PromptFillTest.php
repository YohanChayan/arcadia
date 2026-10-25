<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\Guide;
use App\Models\PromptTemplate;
use App\Models\PromptVariable;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\PromptRenderer;
use Database\Seeders\ArcadiaFlyerPromptTemplateSeeder;
use Database\Seeders\CoverPromptTemplateSeeder;
use Database\Seeders\GuideFlyerPromptTemplateSeeder;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Filling a prompt: a version's declared variables, the one renderer behind the live preview
 * and the copy, and the record a research copy leaves on a guide — which freezes the version it used.
 */
class PromptFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    /** A template whose latest version asks for a topic (required), a level and some notes. */
    private function template(): PromptTemplate
    {
        $template = PromptTemplate::create(['name' => PromptTemplate::RESEARCH]);
        $version = $template->latestVersion();

        $version->sections()->create(['kind' => PromptSectionKind::PERSONA, 'title' => 'Rol', 'body' => 'Responde en {{language}}.', 'position' => 1]);
        $version->sections()->create(['kind' => PromptSectionKind::BRIEF, 'body' => "Tema: {{topic}}\nNivel: {{level}}\nNotas: {{notes}}", 'position' => 2]);

        foreach ([
            ['topic', 'Tema', PromptVariableType::TEXT, true],
            ['level', 'Nivel', PromptVariableType::LEVEL, false],
            ['notes', 'Notas', PromptVariableType::LONGTEXT, false],
            ['language', 'Idioma', PromptVariableType::LANGUAGE, true],
        ] as $position => [$name, $label, $type, $required]) {
            $version->variables()->create(compact('name', 'label', 'type', 'required') + ['position' => $position + 1]);
        }

        return $template;
    }

    /** A Spanish draft guide, written by hand: no request, no prompt copied yet. */
    private function guide(): Guide
    {
        return Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::INTERMEDIATE, 'goals' => ['Conseguir trabajo']]);
    }

    public function test_the_renderer_fills_every_placeholder_in_words(): void
    {
        $version = $this->template()->latestVersion();
        // Choices read in the editor's language — the host's, which a request on `aprende.` forces.
        app()->setLocale('es');

        $filled = app(PromptRenderer::class)->render($version, ['topic' => 'Excel', 'level' => 'basic', 'language' => 'es']);

        $this->assertSame("## Rol\n\nResponde en español.\n\nTema: Excel\nNivel: Básico\nNotas: —", $filled['prompt']);
        $this->assertSame([], $filled['missing']);
    }

    public function test_extra_instructions_close_the_prompt_and_are_kept_with_the_guide(): void
    {
        app()->setLocale('es');
        $template = $this->template();
        $version = $template->latestVersion();

        $filled = app(PromptRenderer::class)->render($version, ['topic' => 'Excel', 'language' => 'es', 'extra_instructions' => 'Solo fuentes en español.']);
        $this->assertStringEndsWith("## Instrucciones extra\n\nSolo fuentes en español.", $filled['prompt']);

        $guide = $this->guide();
        $this->post("/es/editor/guides/{$guide->id}/prompt", [
            'version_id' => $version->id,
            'values' => ['topic' => 'Excel', 'language' => 'es', 'extra_instructions' => 'Solo fuentes en español.'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Solo fuentes en español.', $guide->fresh()->prompt_values['extra_instructions']);

        // No variable may take its name.
        $next = PromptTemplate::create(['name' => 'Otro']);
        $this->post("/es/editor/prompts/{$next->id}/variables", ['name' => 'extra_instructions', 'label' => 'x', 'type' => 'text'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_required_blank_left_empty_stays_visible_and_is_listed(): void
    {
        $version = $this->template()->latestVersion();
        $version->sections()->create(['kind' => PromptSectionKind::COPY, 'body' => 'Para {{learner_name}}', 'position' => 3]);

        $filled = app(PromptRenderer::class)->render($version->fresh(), ['language' => 'es']);

        $this->assertStringContainsString('Tema: {{topic}}', $filled['prompt']);
        $this->assertSame(['topic'], $filled['missing']);
        $this->assertSame(['learner_name'], $filled['undeclared']);
    }

    public function test_the_live_preview_answers_in_json_and_changes_nothing(): void
    {
        $template = $this->template();
        $version = $template->latestVersion();

        $this->postJson("/es/editor/prompts/{$template->id}/fill", [
            'version_id' => $version->id,
            'values' => ['topic' => 'Excel', 'language' => 'en'],
        ])->assertOk()
            ->assertJsonPath('missing', [])
            ->assertJson(fn ($json) => $json->where('prompt', fn ($prompt) => str_contains($prompt, 'Responde en inglés.'))->etc());

        $this->assertFalse($version->fresh()->isFrozen());

        // Another template's version is not this template's to fill.
        $other = PromptTemplate::create(['name' => 'Otro']);
        $this->postJson("/es/editor/prompts/{$template->id}/fill", ['version_id' => $other->latestVersion()->id, 'values' => []])
            ->assertNotFound();
    }

    public function test_variables_are_declared_on_the_latest_version_with_a_unique_lowercase_name(): void
    {
        $template = $this->template();

        $this->post("/es/editor/prompts/{$template->id}/variables", ['name' => 'time_per_day', 'label' => 'Tiempo', 'type' => 'text', 'required' => false])
            ->assertRedirect()->assertSessionHasNoErrors();

        foreach (['topic', 'Topic', '1st', 'with space'] as $name) {
            $this->post("/es/editor/prompts/{$template->id}/variables", ['name' => $name, 'label' => 'x', 'type' => 'text'])
                ->assertSessionHasErrors('name');
        }

        $variable = PromptVariable::where('name', 'time_per_day')->sole();
        $this->put("/es/editor/prompts/{$template->id}/variables/{$variable->id}", ['name' => 'time_per_day', 'label' => 'Tiempo al día', 'type' => 'text', 'required' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($variable->fresh()->required);

        $this->post("/es/editor/prompts/{$template->id}/variables/{$variable->id}/move/up");
        $this->assertSame(4, $variable->fresh()->position);

        $this->delete("/es/editor/prompts/{$template->id}/variables/{$variable->id}")->assertRedirect();
        $this->assertSame(4, PromptVariable::count());
    }

    public function test_the_template_screen_names_placeholders_no_variable_declares(): void
    {
        $template = $this->template();
        $template->latestVersion()->sections()->create(['kind' => PromptSectionKind::COPY, 'body' => '{{context}} y {{topic}}', 'position' => 3]);

        $this->get("/es/editor/prompts/{$template->id}")
            ->assertInertia(fn ($page) => $page->where('undeclared', ['context'])->has('variables', 4)->has('fillVersion.variables', 4));
    }

    public function test_copying_for_a_guide_records_the_prompt_and_freezes_its_version(): void
    {
        $template = $this->template();
        $version = $template->latestVersion();
        $guide = $this->guide();

        $this->post("/es/editor/guides/{$guide->id}/prompt", [
            'version_id' => $version->id,
            'values' => ['topic' => 'Excel', 'language' => 'es', 'stray' => 'ignored'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $guide->refresh();
        $this->assertSame($version->id, $guide->prompt_version_id);
        $this->assertSame(['topic' => 'Excel', 'language' => 'es'], $guide->prompt_values);
        $this->assertTrue($version->fresh()->isFrozen());

        // Frozen: its variables refuse changes, and the template carries on in a new version.
        $variable = $version->variables->first();
        $this->put("/es/editor/prompts/{$template->id}/variables/{$variable->id}", ['name' => 'topic', 'label' => 'x', 'type' => 'text'])
            ->assertStatus(409);
        $this->post("/es/editor/prompts/{$template->id}/versions");
        $this->assertCount(4, $template->latestVersion()->variables);

        // The content screen reopens it on that version, filled as it was, beside the newer one —
        // and offers no template but the research one.
        PromptTemplate::create(['name' => PromptTemplate::COVER]);
        $this->get("/es/editor/guides/{$guide->id}/content")->assertInertia(fn ($page) => $page
            ->where('research.prompt.name', PromptTemplate::RESEARCH)
            ->has('research.prompt.versions', 2)
            ->where('research.versionId', $version->id)
            ->where('research.values.topic', 'Excel')
            ->has('choices.level', 3));

        // Only a version of the research template is recorded.
        $cover = PromptTemplate::where('name', PromptTemplate::COVER)->sole()->latestVersion();
        $this->post("/es/editor/guides/{$guide->id}/prompt", ['version_id' => $cover->id, 'values' => []])
            ->assertNotFound();
    }

    public function test_a_guide_never_copied_for_starts_from_what_it_says(): void
    {
        $this->template();
        $guide = $this->guide();

        $this->get("/es/editor/guides/{$guide->id}/content")->assertInertia(fn ($page) => $page
            ->where('research.versionId', null)
            ->where('research.values', ['topic' => 'Excel', 'level' => 'intermediate', 'goal' => 'Conseguir trabajo', 'language' => 'es']));
    }

    public function test_a_prompt_with_a_required_blank_is_not_recorded(): void
    {
        $version = $this->template()->latestVersion();
        $guide = $this->guide();

        $this->post("/es/editor/guides/{$guide->id}/prompt", ['version_id' => $version->id, 'values' => ['language' => 'es']])
            ->assertSessionHasErrors('values');

        $this->assertNull($guide->fresh()->prompt_version_id);
        $this->assertFalse($version->fresh()->isFrozen());
    }

    public function test_another_languages_guide_cannot_be_recorded_here(): void
    {
        $version = $this->template()->latestVersion();
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel', 'level' => Level::BASIC]);

        $this->post("/es/editor/guides/{$english->id}/prompt", ['version_id' => $version->id, 'values' => ['topic' => 'x', 'language' => 'en']])
            ->assertNotFound();
    }

    public function test_the_starter_template_declares_every_blank_it_uses(): void
    {
        $this->seed(PromptTemplateSeeder::class);

        $version = PromptTemplate::sole()->latestVersion();
        $filled = app(PromptRenderer::class)->render($version, ['topic' => 'Excel para el trabajo', 'level' => 'basic', 'language' => 'es']);

        $this->assertSame([], $filled['undeclared']);
        $this->assertSame([], $filled['missing']);
        $this->assertStringNotContainsString('{{', $filled['prompt']);
        $this->assertStringContainsString('curator_notes', $filled['prompt']);
    }

    public function test_the_cover_template_declares_every_blank_and_asks_for_a_proportion_not_pixels(): void
    {
        $this->seed(CoverPromptTemplateSeeder::class);
        $this->seed(CoverPromptTemplateSeeder::class);

        $version = PromptTemplate::sole()->latestVersion();
        $filled = app(PromptRenderer::class)->render($version, ['title' => 'Excel para el trabajo', 'level' => 'basic']);

        $this->assertSame([], $filled['undeclared']);
        $this->assertSame([], $filled['missing']);
        $this->assertStringNotContainsString('{{', $filled['prompt']);
        $this->assertStringContainsString('Excel para el trabajo', $filled['prompt']);
        // The upload rule's shapes (`ImageSlot::COVER`), and no size a tool would have to obey.
        $this->assertStringContainsString('3:2 o 16:9', $filled['prompt']);
        $this->assertStringContainsString('Ningún texto', $filled['prompt']);
        $this->assertDoesNotMatchRegularExpression('/\d{3,4}\s*[x×]\s*\d{3,4}/', $filled['prompt']);
    }

    public function test_the_cover_template_is_brought_up_to_date_once_and_a_used_version_keeps_its_text(): void
    {
        // As first seeded: the reference photo, and no `style` blank.
        $template = PromptTemplate::create(['name' => PromptTemplate::COVER]);
        $first = $template->latestVersion();
        $first->sections()->create(['kind' => 'persona', 'title' => 'Tu rol', 'body' => 'Te adjunto una imagen de referencia.', 'position' => 1]);
        $first->variables()->create(['name' => 'title', 'label' => 'Título', 'type' => 'text', 'required' => true, 'position' => 1]);
        $first->forceFill(['frozen_at' => now()])->save();

        $this->seed(CoverPromptTemplateSeeder::class);
        $this->seed(CoverPromptTemplateSeeder::class);

        $this->assertSame(2, $template->versions()->count());
        $this->assertStringContainsString('referencia', $first->fresh()->sections()->sole()->body);

        $latest = $template->fresh()->latestVersion();
        $this->assertStringContainsString('el aspecto Arcadia', $latest->sections()->where('kind', 'persona')->sole()->body);
        $this->assertStringNotContainsString('referencia', $latest->sections()->where('kind', 'persona')->sole()->body);
        $this->assertTrue($latest->variables()->where('name', 'style')->exists());
        $this->assertSame(6, $latest->sections()->count());
    }

    public function test_a_line_placeholder_left_empty_takes_its_line_with_it(): void
    {
        $template = PromptTemplate::create(['name' => 'Flyer']);
        $version = $template->latestVersion();
        $version->sections()->create(['kind' => PromptSectionKind::COPY, 'body' => "Titular: {{?headline}}\nWeb: «{{?web}}»\nNotas: {{notes}}", 'position' => 1]);
        $version->variables()->create(['name' => 'headline', 'label' => 'Titular', 'type' => 'text', 'required' => true, 'position' => 1]);
        $version->variables()->create(['name' => 'web', 'label' => 'Web', 'type' => 'text', 'required' => false, 'position' => 2]);
        $version->variables()->create(['name' => 'notes', 'label' => 'Notas', 'type' => 'text', 'required' => false, 'position' => 3]);
        $renderer = app(PromptRenderer::class);

        // An optional one goes with its line; a plain one still reads as a dash.
        $this->assertSame("Titular: Arcadia\nNotas: —", $renderer->render($version, ['headline' => 'Arcadia'])['prompt']);
        $this->assertSame("Titular: Arcadia\nWeb: «arcadia.yhndevside.com»\nNotas: —", $renderer->render($version, ['headline' => 'Arcadia', 'web' => 'arcadia.yhndevside.com'])['prompt']);

        // A required one keeps its line, and is listed.
        $filled = $renderer->render($version, ['web' => 'x']);
        $this->assertStringContainsString('Titular: {{?headline}}', $filled['prompt']);
        $this->assertSame(['headline'], $filled['missing']);
        $this->assertSame(['headline', 'web', 'notes'], $renderer->placeholders($version));
    }

    public function test_a_variable_starts_at_its_default_and_a_price_at_the_hosts(): void
    {
        $template = $this->template();
        $variable = $template->latestVersion()->variables()->where('name', 'notes')->sole();

        $this->put("/es/editor/prompts/{$template->id}/variables/{$variable->id}", ['name' => 'notes', 'label' => 'Notas', 'type' => 'longtext', 'default_value' => 'Solo fuentes en español'])
            ->assertRedirect();
        $this->post("/es/editor/prompts/{$template->id}/variables", ['name' => 'price', 'label' => 'Precio', 'type' => 'price'])
            ->assertRedirect();
        Setting::forLocale('es')->fill(['price' => '2.50', 'price_note' => 'BCV'])->save();

        $this->get("/es/editor/prompts/{$template->id}")->assertInertia(fn ($page) => $page
            ->where('fillVersion.variables.2.default', 'Solo fuentes en español')
            ->where('fillVersion.variables.0.default', null)
            ->where('fillVersion.variables.4.name', 'price')
            ->where('fillVersion.variables.4.default', '$2.50 BCV'));
    }

    public function test_a_step_length_starts_at_the_hosts_setting_and_its_own_default_wins(): void
    {
        $template = $this->template();
        $this->post("/es/editor/prompts/{$template->id}/variables", ['name' => 'step_minutes', 'label' => 'Paso', 'type' => 'step_length'])
            ->assertRedirect();
        Setting::forLocale('es')->fill(['step_min_minutes' => 3, 'step_max_minutes' => 6])->save();

        $this->get("/es/editor/prompts/{$template->id}")->assertInertia(fn ($page) => $page
            ->where('fillVersion.variables.4.name', 'step_minutes')
            ->where('fillVersion.variables.4.default', '3 a 6'));

        $variable = $template->latestVersion()->variables()->where('name', 'step_minutes')->sole();
        $this->put("/es/editor/prompts/{$template->id}/variables/{$variable->id}", ['name' => 'step_minutes', 'label' => 'Paso', 'type' => 'step_length', 'default_value' => '2 a 4'])
            ->assertRedirect();

        $this->get("/es/editor/prompts/{$template->id}")->assertInertia(fn ($page) => $page->where('fillVersion.variables.4.default', '2 a 4'));
    }

    public function test_the_arcadia_flyer_template_starts_filled_and_draws_no_empty_field(): void
    {
        $this->seed(ArcadiaFlyerPromptTemplateSeeder::class);
        $this->seed(ArcadiaFlyerPromptTemplateSeeder::class);

        $version = PromptTemplate::sole()->latestVersion();
        $defaults = $version->variables->pluck('default_value', 'name');
        $filled = app(PromptRenderer::class)->render($version, ['web' => $defaults['web'], 'format' => '4:5', 'language' => 'es']);

        $this->assertSame(1, $version->number);
        // Results, not processes: the headline is the model's unless given, Arcadia only signs.
        $this->assertNull($defaults['headline']);
        $this->assertStringContainsString('compra resultados, no procesos', $filled['prompt']);
        // No topic of its own: the results start empty, and the scene carries no trade.
        $this->assertNull($defaults['situations']);
        $this->assertStringContainsString('sin herramientas ni objetos de ningún oficio', $filled['prompt']);
        // A decided headline, and a layer of mini artefacts of a little of everything.
        $this->assertStringContainsString('«Decídete. Aprende. Transfórmate.»', $filled['prompt']);
        $this->assertStringContainsString('un poco de todo', $filled['prompt']);
        $this->assertStringNotContainsString('Mini artefactos:', $filled['prompt']);
        $this->assertStringContainsString('Firma, pequeña, en una esquina: «Arcadia»', $filled['prompt']);
        $this->assertStringNotContainsString('Titular, lo más grande, en una o dos líneas: «', $filled['prompt']);
        $this->assertSame('arcadia.yhndevside.com', $defaults['web']);
        $this->assertSame(PromptVariableType::PRICE, $version->variables->firstWhere('name', 'price')->type);
        $this->assertSame([], $filled['undeclared']);
        $this->assertSame([], $filled['missing']);
        $this->assertStringNotContainsString('{{', $filled['prompt']);
        $this->assertStringContainsString('«arcadia.yhndevside.com»', $filled['prompt']);
        // An empty contact or price never reaches the model, not even as a dash.
        $this->assertStringNotContainsString('«—»', $filled['prompt']);
        // An empty WhatsApp leaves no trace at all: not its line, not its name in another rule.
        $this->assertStringNotContainsStringIgnoringCase('whatsapp', $filled['prompt']);
        $this->assertDoesNotMatchRegularExpression('/\d{3,4}\s*[x×]\s*\d{3,4}/', $filled['prompt']);
    }

    public function test_the_arcadia_flyer_seeded_before_its_second_text_gets_it_as_a_new_version(): void
    {
        $template = PromptTemplate::create(['name' => ArcadiaFlyerPromptTemplateSeeder::NAME]);
        $first = $template->latestVersion();
        $first->sections()->create(['kind' => 'copy', 'title' => 'Texto exacto', 'body' => 'Cualquier dato que aparezca como «—» se omite.', 'position' => 1]);

        $this->seed(ArcadiaFlyerPromptTemplateSeeder::class);
        $this->seed(ArcadiaFlyerPromptTemplateSeeder::class);

        $this->assertSame(2, $template->versions()->count());
        $this->assertStringContainsString('«—»', $first->fresh()->sections()->sole()->body);
        $latest = $template->fresh()->latestVersion();
        $this->assertStringContainsString('{{?whatsapp}}', $latest->sections()->where('title', 'Texto exacto')->sole()->body);
        $this->assertStringContainsString('mini artefactos', $latest->sections()->where('kind', 'structure')->sole()->body);
        $this->assertTrue($latest->variables()->where('name', 'artifacts')->exists());
        $this->assertSame(PromptVariableType::PHONE, $latest->variables()->where('name', 'whatsapp')->sole()->type);
    }

    public function test_the_guide_flyer_template_declares_every_blank_and_draws_no_empty_field(): void
    {
        $this->seed(GuideFlyerPromptTemplateSeeder::class);
        $this->seed(GuideFlyerPromptTemplateSeeder::class);

        $version = PromptTemplate::sole()->latestVersion();
        $filled = app(PromptRenderer::class)->render($version, [
            'title' => 'USDT y Binance P2P sin miedo: cobrar, cambiar y no caer en estafas',
            'goals' => "Cobrar en USDT\nCambiar sin perder",
            'format' => '4:5',
            'language' => 'es',
            'web' => 'arcadia.yhndevside.com',
        ]);

        $this->assertSame(1, $version->number);
        $this->assertSame([], $filled['undeclared']);
        $this->assertSame([], $filled['missing']);
        $this->assertStringNotContainsString('{{', $filled['prompt']);
        $this->assertStringContainsString('Cobrar en USDT', $filled['prompt']);
        $this->assertStringContainsString('compra resultados, no procesos', $filled['prompt']);
        $this->assertStringContainsString('«arcadia.yhndevside.com»', $filled['prompt']);
        // Empty price, contact, subtitle and scene never reach the model, not even as a dash.
        $this->assertStringNotContainsStringIgnoringCase('whatsapp', $filled['prompt']);
        $this->assertStringNotContainsString('«—»', $filled['prompt']);
        $this->assertStringNotContainsString('Idea de escena', $filled['prompt']);
        $this->assertDoesNotMatchRegularExpression('/\d{3,4}\s*[x×]\s*\d{3,4}/', $filled['prompt']);
    }

    public function test_a_guide_flyer_seeded_with_its_first_text_gets_the_results_approach_as_v2(): void
    {
        $template = PromptTemplate::create(['name' => GuideFlyerPromptTemplateSeeder::NAME]);
        $first = $template->latestVersion();
        $first->sections()->create(['kind' => 'copy', 'title' => 'Texto exacto', 'body' => 'Subtítulo: «{{?subtitle}}»', 'position' => 1]);
        $first->freeze();

        $this->seed(GuideFlyerPromptTemplateSeeder::class);
        $this->seed(GuideFlyerPromptTemplateSeeder::class);

        $this->assertSame(2, $template->versions()->count());
        $this->assertSame('Subtítulo: «{{?subtitle}}»', $first->fresh()->sections()->sole()->body);
        $latest = $template->fresh()->latestVersion();
        $this->assertTrue($latest->variables()->where('name', 'headline')->exists());
        $this->assertTrue($latest->variables()->where('name', 'artifacts')->exists());
        $this->assertStringContainsString('mini artefactos', $latest->sections()->where('kind', 'structure')->sole()->body);
    }

    public function test_a_phone_renders_grouped_with_dashes(): void
    {
        $template = PromptTemplate::create(['name' => 'Flyer']);
        $version = $template->latestVersion();
        $version->sections()->create(['kind' => PromptSectionKind::COPY, 'body' => '«{{?whatsapp}}»', 'position' => 1]);
        $version->variables()->create(['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => PromptVariableType::PHONE, 'required' => false, 'position' => 1]);
        $render = fn (string $phone) => app(PromptRenderer::class)->render($version, ['whatsapp' => $phone])['prompt'];

        $this->assertSame('«0412-802-3555»', $render('04128023555'));
        $this->assertSame('«0412-802-3555»', $render('0412 802 35 55'));
        $this->assertSame('«+58 412-802-3555»', $render('+584128023555'));
        // A number in another shape is the owner's to write.
        $this->assertSame('«+1 555 0100»', $render('+1 555 0100'));
    }
}
