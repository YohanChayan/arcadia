<?php

namespace Tests\Feature;

use App\Actions\Content\DraftNextVersion;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Jobs\IndexGuideJob;
use App\Knowledge\ChunkType;
use App\Knowledge\GuideCatalogue\GuideIndexer;
use App\Knowledge\KnowledgeChunk;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;
use Throwable;

/**
 * The knowledge index follows the guides: a passage is embedded when it is new or its text
 * changed, and removed when the guide no longer has it. Runs against Postgres + pgvector's test
 * database (`arcadia_vectors_testing`); without it, these tests are skipped.
 */
class KnowledgeIndexTest extends TestCase
{
    use RefreshDatabase;

    /** How many passages each embedding call was asked for, in order. */
    private array $embedded = [];

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

        Embeddings::fake(function (EmbeddingsPrompt $prompt) {
            $this->embedded[] = count($prompt->inputs);

            return array_map(fn () => Embeddings::fakeEmbedding($prompt->dimensions), $prompt->inputs);
        });
    }

    private function guide(string $title = 'Excel desde cero', bool $open = true): Guide
    {
        $guide = Guide::create(['locale' => 'es', 'title' => $title, 'level' => Level::BASIC]);
        $guide->update(['status' => GuideStatus::PUBLISHED, 'open_for_enrollment' => $open, 'goals' => ['Hacer tablas']]);
        $version = $guide->latestVersion();
        $version->stages()->create(['title' => 'Uno', 'goal' => 'Al terminar: primero.', 'position' => 1]);
        $version->stages()->create(['title' => 'Dos', 'goal' => 'Segundo.', 'position' => 2]);
        $version->release();

        return $guide;
    }

    public function test_a_rebuild_embeds_every_passage_of_every_published_guide(): void
    {
        $this->guide();
        $this->guide('Cerrada', open: false);

        $counts = app(GuideIndexer::class)->rebuild();

        $this->assertSame(['embedded' => 6, 'kept' => 0, 'deleted' => 0], $counts);
        $this->assertSame([6], $this->embedded, 'One call for the whole batch.');

        $stage = KnowledgeChunk::query()->where('guide_title', 'Excel desde cero')->where('stage_position', 1)->firstOrFail();
        $this->assertSame(ChunkType::STAGE, $stage->chunk_type);
        $this->assertCount(768, $stage->embedding);
        $this->assertSame('gemini-embedding-2', $stage->embedding_model);
        $this->assertSame(1, KnowledgeChunk::query()->where('listed', false)->where('chunk_type', 'guide')->count());
    }

    public function test_an_unchanged_passage_is_not_embedded_again(): void
    {
        $this->guide();
        app(GuideIndexer::class)->rebuild();

        $counts = app(GuideIndexer::class)->rebuild();

        $this->assertSame(['embedded' => 0, 'kept' => 3, 'deleted' => 0], $counts);
        $this->assertSame([3], $this->embedded);
    }

    public function test_a_new_release_embeds_only_what_changed_and_drops_a_removed_stage(): void
    {
        $guide = $this->guide();
        app(GuideIndexer::class)->index($guide->id);

        $draft = app(DraftNextVersion::class)->handle($guide);
        $draft->stages()->where('position', 2)->delete();
        $draft->stages()->where('position', 1)->update(['goal' => 'Una meta nueva.']);
        $draft->release();

        $counts = app(GuideIndexer::class)->index($guide->id);

        // The guide passage and stage 1 changed; stage 2 is gone.
        $this->assertSame(['embedded' => 2, 'kept' => 0, 'deleted' => 1], $counts);
        $this->assertSame(2, KnowledgeChunk::query()->where('guide_id', $guide->id)->count());
    }

    public function test_closing_a_guide_updates_its_passages_without_embedding_them_again(): void
    {
        $guide = $this->guide();
        app(GuideIndexer::class)->index($guide->id);

        $guide->update(['open_for_enrollment' => false]);
        $counts = app(GuideIndexer::class)->index($guide->id);

        $this->assertSame(0, $counts['embedded']);
        $this->assertSame(0, KnowledgeChunk::query()->where('guide_id', $guide->id)->where('listed', true)->count());
    }

    public function test_keeping_a_guide_out_of_the_catalogue_unlists_its_passages(): void
    {
        $guide = $this->guide();
        app(GuideIndexer::class)->index($guide->id);

        $guide->update(['in_catalogue' => false]);
        $counts = app(GuideIndexer::class)->index($guide->id);

        $this->assertSame(0, $counts['embedded']);
        $this->assertSame(0, KnowledgeChunk::query()->where('guide_id', $guide->id)->where('listed', true)->count());
    }

    public function test_an_unpublished_or_deleted_guide_leaves_no_passage(): void
    {
        $unpublished = $this->guide();
        $deleted = $this->guide('Borrada');
        app(GuideIndexer::class)->rebuild();

        $unpublished->update(['status' => GuideStatus::DRAFT]);
        app(GuideIndexer::class)->index($unpublished->id);
        $deletedId = $deleted->id;
        $deleted->delete();
        app(GuideIndexer::class)->index($deletedId);

        $this->assertSame(0, KnowledgeChunk::query()->count());
    }

    public function test_a_release_or_a_change_to_the_promise_queues_the_guides_reindexing(): void
    {
        config(['knowledge.index_on_change' => true]);
        Queue::fake();

        $guide = $this->guide();
        Queue::assertPushedOn('knowledge', IndexGuideJob::class, fn (IndexGuideJob $job) => $job->guideId === $guide->id);

        Queue::fake();
        $guide->update(['greeting' => 'Hola']);
        Queue::assertNotPushed(IndexGuideJob::class);

        $guide->update(['pitch' => 'Aprende Excel en una semana.']);
        Queue::assertPushed(IndexGuideJob::class);
    }

    public function test_the_command_rebuilds_the_index(): void
    {
        $this->guide();

        $this->artisan('knowledge:rebuild')
            ->expectsOutputToContain('Embedded 3, unchanged 0, deleted 0.')
            ->assertSuccessful();
    }
}
