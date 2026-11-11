<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Knowledge\ChunkType;
use App\Knowledge\Contracts\Retriever;
use App\Knowledge\GuideCatalogue\GuideIndexer;
use App\Knowledge\KnowledgeChunk;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;
use App\Knowledge\Retrievers\CompositeRetriever;
use App\Knowledge\Retrievers\GuideCatalogueRetriever;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;
use Throwable;

/**
 * A question finds the guide catalogue's closest passages in pgvector, in its own locale and, by
 * default, among listed guides only. Embeddings are faked by topic — a text about Excel and a
 * question about Excel get the same vector — so closeness is known in advance. Runs against
 * Postgres + pgvector's test database; without it, these tests are skipped.
 */
class KnowledgeRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private const TOPICS = ['excel', 'git'];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('vectors')->getPdo();
        } catch (Throwable) {
            $this->markTestSkipped('The vector store is not reachable: docker compose up -d pgvector.');
        }

        Artisan::call('migrate', ['--database' => 'vectors', '--path' => 'database/migrations/vectors', '--force' => true]);
        KnowledgeChunk::query()->delete();

        Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(fn (string $text) => $this->topicVector($text, $prompt->dimensions), $prompt->inputs));
    }

    /** @return list<float> a unit vector pointing at the first topic the text names */
    private function topicVector(string $text, int $dimensions): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $topic = collect(self::TOPICS)->search(fn (string $topic) => str_contains(mb_strtolower($text), $topic));
        $vector[$topic === false ? count(self::TOPICS) : $topic] = 1.0;

        return $vector;
    }

    private function guide(string $title, string $locale = 'es', bool $open = true): Guide
    {
        $guide = Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC]);
        $guide->update(['status' => GuideStatus::PUBLISHED, 'open_for_enrollment' => $open, 'goals' => ['Aprender']]);
        $guide->latestVersion()->stages()->create(['title' => 'Primeros pasos', 'goal' => 'Empiezas.', 'position' => 1]);
        $guide->latestVersion()->release();

        return $guide;
    }

    private function retrieve(string $text, string $locale = 'es', int $limit = 5, bool $listedOnly = true): RetrievedPassages
    {
        return app(GuideCatalogueRetriever::class)->retrieve(new RetrievalQuery($text, $locale, $limit, $listedOnly));
    }

    public function test_a_question_finds_the_passages_of_the_guide_it_is_about_closest_first(): void
    {
        $excel = $this->guide('Excel desde cero');
        $this->guide('Git sin miedo');
        app(GuideIndexer::class)->rebuild();

        $passages = $this->retrieve('¿Cómo hago tablas en Excel?', limit: 2);

        $this->assertCount(2, $passages);
        $passages->each(function (RetrievedPassage $passage) use ($excel): void {
            $this->assertSame($excel->id, $passage->guideId);
            $this->assertSame('Excel desde cero', $passage->guideTitle);
            $this->assertSame(1.0, $passage->score);
            $this->assertSame(GuideIndexer::SOURCE, $passage->source);
        });
        $this->assertEqualsCanonicalizing([ChunkType::GUIDE, ChunkType::STAGE], $passages->pluck('chunkType')->all());
        $this->assertSame(['stagePosition' => 1], $passages->firstWhere('chunkType', ChunkType::STAGE)->metadata);
    }

    public function test_only_listed_guides_in_the_questions_locale_are_searched_by_default(): void
    {
        $this->guide('Excel cerrada', open: false);
        $this->guide('Excel in English', locale: 'en');
        app(GuideIndexer::class)->rebuild();

        $this->assertCount(0, $this->retrieve('excel'));
        $this->assertSame(['Excel cerrada'], $this->retrieve('excel', listedOnly: false)->pluck('guideTitle')->unique()->values()->all());
        $this->assertSame(['Excel in English'], $this->retrieve('excel', locale: 'en')->pluck('guideTitle')->unique()->values()->all());
    }

    public function test_an_empty_question_finds_nothing_and_asks_no_embedding(): void
    {
        $this->guide('Excel desde cero');
        app(GuideIndexer::class)->rebuild();
        Embeddings::fake()->preventStrayEmbeddings();

        $this->assertCount(0, $this->retrieve('   '));
    }

    public function test_the_composite_keeps_the_closest_passages_across_corpora(): void
    {
        $corpus = fn (string $source, float ...$scores) => new class($source, $scores) implements Retriever
        {
            /** @param  list<float>  $scores */
            public function __construct(private string $source, private array $scores) {}

            public function retrieve(RetrievalQuery $query): RetrievedPassages
            {
                return new RetrievedPassages(array_map(fn (float $score) => new RetrievedPassage('x', $score, $this->source, 1, 'Guía', ChunkType::GUIDE, 'es'), $this->scores));
            }
        };

        $passages = (new CompositeRetriever([$corpus('a', 0.9, 0.2), $corpus('b', 0.5)]))->retrieve(new RetrievalQuery('x', 'es', limit: 2));

        $this->assertSame([0.9, 0.5], $passages->pluck('score')->all());
        $this->assertSame(['a', 'b'], $passages->pluck('source')->all());
    }

    public function test_the_contract_resolves_to_the_catalogue_unless_set_to_fake(): void
    {
        config(['knowledge.retriever' => 'catalogue']);

        $this->assertInstanceOf(CompositeRetriever::class, app(Retriever::class));
    }
}
