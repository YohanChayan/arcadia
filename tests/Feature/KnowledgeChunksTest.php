<?php

namespace Tests\Feature;

use App\Actions\Content\DraftNextVersion;
use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\SectionKind;
use App\Knowledge\Chunk;
use App\Knowledge\ChunkType;
use App\Knowledge\GuideCatalogue\GuideChunker;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The knowledge index says what a guide promises, never what is in it (ADR 0022): one chunk for
 * the guide and one per stage, read from the released version only.
 */
class KnowledgeChunksTest extends TestCase
{
    use RefreshDatabase;

    private function guide(string $title = 'Excel desde cero', bool $open = true, GuideStatus $status = GuideStatus::PUBLISHED): Guide
    {
        $guide = Guide::create(['locale' => 'es', 'title' => $title, 'level' => Level::BASIC]);
        $guide->update(['status' => $status, 'open_for_enrollment' => $open, 'goals' => ['Hacer tablas', 'Usar fórmulas']]);

        return $guide;
    }

    /** @return list<Chunk> */
    private function chunks(Guide $guide): array
    {
        return app(GuideChunker::class)->for($guide->fresh());
    }

    public function test_a_guide_gives_one_chunk_and_one_per_stage_each_under_its_title(): void
    {
        $guide = $this->guide();
        $version = $guide->latestVersion();
        $version->stages()->create(['title' => 'Arrancar sin miedo', 'goal' => 'Al terminar: abres un libro y escribes datos.', 'position' => 1]);
        $version->stages()->create(['title' => 'Fórmulas', 'goal' => 'Sumas una lista.', 'position' => 3]);
        $version->release();

        [$whole, $first, $second] = $this->chunks($guide);

        $this->assertSame(ChunkType::GUIDE, $whole->chunkType);
        $this->assertStringContainsString('Guía: Excel desde cero', $whole->content);
        $this->assertStringContainsString('Nivel: Básico', $whole->content);
        $this->assertStringContainsString("- Hacer tablas\n- Usar fórmulas", $whole->content);
        $this->assertStringContainsString("1. Arrancar sin miedo: Abres un libro y escribes datos.\n2. Fórmulas: Sumas una lista.", $whole->content);
        $this->assertStringNotContainsString('Presentación', $whole->content, 'An empty pitch is left out.');

        $this->assertSame(ChunkType::STAGE, $second->chunkType);
        $this->assertSame("Guía: Excel desde cero\nEtapa 2 de 2: Fórmulas\nAl terminarla: Sumas una lista.", $second->content);
        $this->assertSame(2, $second->stagePosition);
        $this->assertNotSame($first->identity(), $second->identity());
        $this->assertTrue($whole->listed);
        $this->assertSame('es', $whole->locale);
    }

    public function test_only_the_released_version_is_read_never_a_newer_draft(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->stages()->create(['title' => 'Publicada', 'goal' => 'Lo liberado.', 'position' => 1]);
        $guide->latestVersion()->release();
        $draft = app(DraftNextVersion::class)->handle($guide);
        $draft->stages()->create(['title' => 'Borrador', 'goal' => 'Aún no liberado.', 'position' => 2]);

        $content = collect($this->chunks($guide))->pluck('content')->implode("\n");

        $this->assertStringContainsString('Publicada', $content);
        $this->assertStringNotContainsString('Borrador', $content);
    }

    public function test_a_stage_keeps_its_identity_across_versions(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->stages()->create(['title' => 'Uno', 'goal' => 'Algo.', 'position' => 1]);
        $guide->latestVersion()->release();
        $before = $this->chunks($guide)[1]->identity();

        app(DraftNextVersion::class)->handle($guide)->release();

        $this->assertSame($before, $this->chunks($guide)[1]->identity());
    }

    public function test_nothing_below_a_stage_reaches_a_chunk(): void
    {
        $guide = $this->guide();
        $version = $guide->latestVersion();
        $version->sections()->create(['kind' => SectionKind::MUST_MASTER, 'body' => 'Texto de sección privado', 'position' => 1]);
        $stage = $version->stages()->create(['title' => 'Etapa', 'goal' => 'Una meta.', 'position' => 1]);
        $card = $stage->cards()->create(['kind' => CardKind::SOURCE, 'byline' => 'El Tío Tech', 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::YOUTUBE_VIDEO, 'title' => 'Video secreto', 'url' => 'https://youtube.com/watch?v=abc', 'position' => 1]);
        $version->release();

        $content = collect($this->chunks($guide))->pluck('content')->implode("\n");

        foreach (['Texto de sección privado', 'El Tío Tech', 'Video secreto', 'youtube.com'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content);
        }
    }

    public function test_a_released_version_without_stages_still_gives_the_guide_chunk(): void
    {
        $guide = $this->guide();
        $guide->latestVersion()->release();

        $chunks = $this->chunks($guide);

        $this->assertCount(1, $chunks);
        $this->assertStringNotContainsString('Etapas', $chunks[0]->content);
    }

    public function test_drafts_and_unreleased_guides_give_nothing_and_closed_ones_are_not_listed(): void
    {
        $draft = $this->guide('Borrador', status: GuideStatus::DRAFT);
        $draft->latestVersion()->release();
        $unreleased = $this->guide('Sin liberar');
        $closed = $this->guide('Cerrada', open: false);
        $closed->latestVersion()->release();

        $this->assertSame([], $this->chunks($draft));
        $this->assertSame([], $this->chunks($unreleased));
        $this->assertFalse($this->chunks($closed)[0]->listed);
        $this->assertSame(['Cerrada'], app(GuideChunker::class)->all()->pluck('guideTitle')->all());
    }

    public function test_a_stage_goal_loses_its_shared_opening(): void
    {
        $this->assertSame('Lees el historial.', GuideChunker::goal('Al terminar: lees el historial.'));
        $this->assertSame('You read the history.', GuideChunker::goal('By the end, you read the history.'));
        $this->assertSame('Sin prefijo.', GuideChunker::goal('Sin prefijo.'));
    }
}
