<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * An AI's JSON answer brought into a guide's draft: checked first, with every problem at its
 * place; written all at once, keys minted by the app; never into a released version, and never
 * mixed with content already there.
 */
class GuideImportTest extends TestCase
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

    private function guide(): Guide
    {
        return Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC]);
    }

    /** An answer in the shape the starter template's contract asks for. */
    private function answer(array $override = []): array
    {
        return array_replace([
            'title' => 'Excel para tu entrevista',
            'level' => 'intermediate',
            'goals' => ['Conseguir trabajo', 'Algo nuevo'],
            'greeting' => 'Ana, esta es tu ruta.',
            'sections' => [
                ['kind' => 'how_to_use', 'title' => 'Cómo usar esta guía', 'body' => 'En orden.'],
            ],
            'stages' => [
                [
                    'title' => 'Arrancar',
                    'goal' => 'te mueves en Excel sin miedo',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Dostin Hurtado · un video',
                            'description' => 'Lo básico de un tirón.',
                            'quote' => '',
                            'items' => [
                                ['ref' => 's1-1', 'kind' => 'youtube_video', 'title' => 'Excel en un video', 'url' => 'https://www.youtube.com/watch?v=abc123', 'hint' => 'Video · 2 h'],
                                ['ref' => 's1-2', 'kind' => 'note', 'title' => 'Con un cafecito', 'url' => '', 'body' => 'Ve pausando.'],
                            ],
                        ],
                        [
                            'kind' => 'challenge',
                            'description' => 'El reto',
                            'items' => [['ref' => 'reto-1', 'kind' => 'practice', 'title' => 'Lista de precios', 'body' => '=A2*$B$1']],
                        ],
                    ],
                ],
            ],
            'curator_notes' => ['discarded' => [['resource' => 'Serie de inventario', 'reason' => 'usa macros']], 'unverified' => ['Duración real']],
        ], $override);
    }

    /** The answer as the owner saved it: a `.json` file. */
    private function upload(string $json, string $name = 'respuesta.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $json);
    }

    private function preview(Guide $guide, string $json)
    {
        return $this->postJson("/es/editor/guides/{$guide->id}/import/preview", ['file' => $this->upload($json)]);
    }

    public function test_a_valid_answer_is_summarised_before_anything_is_written(): void
    {
        $guide = $this->guide();

        // As an AI tends to hand it over: a sentence, then a fenced block.
        $pasted = "Aquí está tu guía:\n```json\n".json_encode($this->answer())."\n```\n¡Éxitos!";

        $this->preview($guide, $pasted)->assertOk()
            ->assertJsonPath('problems', [])
            ->assertJsonPath('summary.stages', 1)
            ->assertJsonPath('summary.items', 3)
            ->assertJsonPath('summary.links', 1)
            ->assertJsonPath('summary.challenges', 1)
            // Every goal the answer writes, as it will be stored: none dropped.
            ->assertJsonPath('summary.goals', ['Conseguir trabajo', 'Algo nuevo'])
            ->assertJsonPath('has_content', false);

        $this->assertSame(0, Stage::count());
    }

    public function test_every_problem_is_reported_at_its_place(): void
    {
        $guide = $this->guide();
        $answer = $this->answer();
        $answer['stages'][0]['cards'][0]['items'][0]['url'] = '';
        $answer['stages'][0]['cards'][0]['items'][1]['kind'] = 'podcast';
        $answer['stages'][0]['cards'][1]['items'][0]['ref'] = 's1-1';

        $problems = collect($this->preview($guide, json_encode($answer))->assertOk()->json('problems'));

        $this->assertTrue($problems->contains(fn ($p) => str_starts_with($p['place'], 'Etapa 1 · tarjeta 1 · ítem 1 · url')));
        $this->assertTrue($problems->contains(fn ($p) => $p['place'] === 'Etapa 1 · tarjeta 1 · ítem 2 · kind'));
        $this->assertTrue($problems->contains(fn ($p) => str_ends_with($p['place'], 'ref')));

        $this->preview($guide, 'no hay json aquí')->assertJsonPath('problems.0.message', __('arcadia.editor.import.errors.no_json', [], 'es'));
    }

    public function test_only_a_json_file_is_read(): void
    {
        $guide = $this->guide();
        $url = "/es/editor/guides/{$guide->id}/import/preview";

        $this->postJson($url, [])->assertJsonValidationErrors(['file' => __('arcadia.editor.import.errors.no_file', [], 'es')]);
        $this->postJson($url, ['file' => $this->upload(json_encode($this->answer()), 'respuesta.txt')])
            ->assertJsonValidationErrors(['file' => __('arcadia.editor.import.errors.not_json_file', [], 'es')]);
        $this->postJson($url, ['file' => UploadedFile::fake()->create('respuesta.json', 600, 'application/json')])
            ->assertJsonValidationErrors(['file' => __('arcadia.editor.import.errors.too_large', [], 'es')]);
    }

    public function test_importing_writes_the_whole_tree_with_keys_minted_by_the_app(): void
    {
        $guide = $this->guide();

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer())), 'apply_details' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        $draft = $guide->latestVersion();
        $this->assertSame(['Cómo usar esta guía'], $draft->sections()->pluck('title')->all());
        $this->assertSame(['Arrancar'], $draft->stages()->pluck('title')->all());

        $items = Item::query()->where('version_id', $draft->id)->orderBy('position')->get();
        $this->assertCount(3, $items);
        $this->assertNotContains('s1-1', $items->pluck('key')->all());
        $this->assertCount(3, $items->pluck('key')->unique());
        $this->assertNull($items->firstWhere('kind.value', 'note')->url);

        $guide->refresh();
        $this->assertSame('Excel para tu entrevista', $guide->title);
        $this->assertSame(Level::INTERMEDIATE, $guide->level);
        $this->assertSame(['Conseguir trabajo', 'Algo nuevo'], $guide->goals);
        $this->assertSame('Ana, esta es tu ruta.', $guide->greeting);
        $this->assertSame('usa macros', $guide->curator_notes['discarded'][0]['reason']);
        $this->assertSame('draft', $guide->status->value);
    }

    public function test_a_links_brief_is_kept_for_the_owner_through_versions_and_repairs_and_never_reaches_a_reader(): void
    {
        $guide = $this->guide();
        $answer = $this->answer();
        $answer['stages'][0]['cards'][0]['items'][0]['brief'] = [
            'summary' => '  Lo básico de Excel en un solo video. ',
            'covers' => ['Celdas y rangos', '', 'SUMA y PROMEDIO'],
            'duration' => '2 h',
            'search' => 'excel desde cero español',
            'invented' => 'lo que un AI añade de más',
        ];
        // A brief on a line that is not a link is not kept.
        $answer['stages'][0]['cards'][0]['items'][1]['brief'] = ['summary' => 'Nada'];

        $this->preview($guide, json_encode($answer))->assertJsonPath('summary.briefs', 1);
        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($answer))])->assertSessionHasNoErrors();

        $video = Item::query()->where('version_id', $guide->latestVersion()->id)->where('kind', 'youtube_video')->sole();
        $this->assertSame([
            'summary' => 'Lo básico de Excel en un solo video.',
            'covers' => ['Celdas y rangos', 'SUMA y PROMEDIO'],
            'duration' => '2 h',
            'search' => 'excel desde cero español',
        ], $video->brief);
        $this->assertNull(Item::query()->where('kind', 'note')->sole()->brief);

        // Released, then a new version: the copy carries it; a repair swaps the link and keeps it.
        $this->post("/es/editor/guides/{$guide->id}/versions/release");
        $this->post("/es/editor/guides/{$guide->id}/versions");
        $this->put("/es/editor/guides/{$guide->id}/items/{$video->key}/fix", [
            'title' => 'Otro video', 'url' => 'https://www.youtube.com/watch?v=new', 'is_available' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Item::query()->where('key', $video->key)->count());
        Item::query()->where('key', $video->key)->each(fn (Item $copy) => $this->assertSame('Lo básico de Excel en un solo video.', $copy->brief['summary']));

        // The reader's page carries no brief.
        $guide->update(['status' => 'published']);
        $this->get('/guia/'.$guide->link_token)
            ->assertInertia(fn ($page) => $page->missing('stages.0.cards.0.items.0.brief'));
    }

    public function test_a_videos_segment_becomes_its_clip_only_with_evidence(): void
    {
        $guide = $this->guide();
        $answer = $this->answer();
        $items = &$answer['stages'][0]['cards'][0]['items'];
        $items[0]['segment'] = ['start' => '14:00', 'end' => '18:00', 'evidence' => ' Chapter "14:00 Fórmulas" in the description '];
        $items[] = ['ref' => 's1-3', 'kind' => 'youtube_video', 'title' => 'Sin pruebas', 'url' => 'https://www.youtube.com/watch?v=def456', 'segment' => ['start' => 60, 'end' => 300]];
        $items[] = ['ref' => 's1-4', 'kind' => 'youtube_video', 'title' => 'Al revés', 'url' => 'https://www.youtube.com/watch?v=ghi789', 'segment' => ['start' => '5:00', 'end' => '2:00', 'evidence' => 'Chapter']];
        $items[] = ['ref' => 's1-5', 'kind' => 'youtube_video', 'title' => 'Hasta el final', 'url' => 'https://www.youtube.com/watch?v=jkl012', 'segment' => ['start' => '1:02:49', 'evidence' => 'Transcript: "now the pivot tables"']];
        $items[] = ['ref' => 's1-6', 'kind' => 'external_site', 'title' => 'Un sitio', 'url' => 'https://example.com', 'segment' => ['start' => 10, 'evidence' => 'x']];
        unset($items);

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($answer))])->assertSessionHasNoErrors();

        $clip = fn (string $title) => Item::query()->where('title', $title)->sole()->only(Item::CLIP_FIELDS);
        $none = ['clip_start' => null, 'clip_end' => null, 'clip_evidence' => null];

        $this->assertSame(['clip_start' => 840, 'clip_end' => 1080, 'clip_evidence' => 'Chapter "14:00 Fórmulas" in the description'], $clip('Excel en un video'));
        $this->assertSame($none, $clip('Sin pruebas'));
        $this->assertSame($none, $clip('Al revés'));
        $this->assertSame(['clip_start' => 3769, 'clip_end' => null, 'clip_evidence' => 'Transcript: "now the pivot tables"'], $clip('Hasta el final'));
        $this->assertSame($none, $clip('Un sitio'));
    }

    public function test_without_apply_details_the_guide_keeps_its_own_title_and_level(): void
    {
        $guide = $this->guide();

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer()))]);

        $guide->refresh();
        $this->assertSame('Excel', $guide->title);
        $this->assertSame(Level::BASIC, $guide->level);
        $this->assertSame('Ana, esta es tu ruta.', $guide->greeting);
    }

    public function test_content_already_there_is_replaced_only_when_asked(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->stages()->create(['title' => 'A mano', 'position' => 1]);

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer()))])
            ->assertSessionHasErrors('replace');
        $this->assertSame(['A mano'], Stage::pluck('title')->all());

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer())), 'replace' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Arrancar'], Stage::pluck('title')->all());
    }

    public function test_a_released_version_is_never_imported_into(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->release();

        $this->preview($guide, json_encode($this->answer()))->assertStatus(409);
        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer()))])->assertStatus(409);
    }

    public function test_an_answer_with_problems_is_never_written(): void
    {
        $guide = $this->guide();

        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer(['stages' => []])))])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Stage::count());
    }

    public function test_the_reader_sees_the_greeting_and_never_the_curator_notes(): void
    {
        $guide = $this->guide();
        $this->post("/es/editor/guides/{$guide->id}/import", ['file' => $this->upload(json_encode($this->answer()))]);

        $this->get("/es/editor/guides/{$guide->id}/preview")
            ->assertInertia(fn ($page) => $page
                ->where('guide.greeting', 'Ana, esta es tu ruta.')
                ->missing('guide.curator_notes'));
    }
}
