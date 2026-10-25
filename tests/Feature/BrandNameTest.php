<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use Database\Seeders\ArcadiaFlyerPromptTemplateSeeder;
use Database\Seeders\CoverPromptTemplateSeeder;
use Database\Seeders\GuideFlyerPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * The guides product is called Arcadia. It was Studia until 2026-10-21, in its copy and in its
 * code. This holds the line on what a person reads: no copy, manifest or seeded prompt may say
 * Studia again.
 *
 * Whole words and case-sensitive on purpose: `estudia` is Spanish and must never match.
 */
class BrandNameTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_NAME = '/(?<![\w\\\\])(Studia|STUDIA)\b/';

    public function test_no_translation_string_names_the_old_brand(): void
    {
        foreach (['en', 'es'] as $locale) {
            foreach (glob(lang_path($locale).'/{,*/}*.php', GLOB_BRACE) as $file) {
                foreach (Arr::dot(require $file) as $key => $value) {
                    if (is_string($value)) {
                        $this->assertDoesNotMatchRegularExpression(self::OLD_NAME, $value, "{$file} › {$key}");
                    }
                }
            }
        }
    }

    public function test_the_web_manifest_names_arcadia(): void
    {
        $manifest = json_decode(file_get_contents(public_path('img/arcadia/site.webmanifest')), true);

        $this->assertSame(['Arcadia', 'Arcadia'], [$manifest['name'], $manifest['short_name']]);
    }

    public function test_no_seeded_prompt_names_the_old_brand_and_the_spanish_word_survives(): void
    {
        $this->seed([CoverPromptTemplateSeeder::class, GuideFlyerPromptTemplateSeeder::class, ArcadiaFlyerPromptTemplateSeeder::class]);

        $text = PromptTemplate::with('versions.sections', 'versions.variables')->get()
            ->flatMap(fn ($template) => [$template->name, $template->description, ...$template->versions->flatMap(fn ($version) => [
                $version->description,
                ...$version->sections->flatMap(fn ($section) => [$section->title, $section->body]),
                ...$version->variables->flatMap(fn ($variable) => [$variable->label, $variable->default_value]),
            ])])
            ->filter()
            ->implode("\n");

        $this->assertDoesNotMatchRegularExpression(self::OLD_NAME, $text);
        $this->assertStringContainsString('Arcadia', $text);
        $this->assertSame('Flyer de Arcadia — imagen', ArcadiaFlyerPromptTemplateSeeder::NAME);
    }
}
