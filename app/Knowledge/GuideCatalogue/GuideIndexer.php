<?php

namespace App\Knowledge\GuideCatalogue;

use App\Knowledge\Chunk;
use App\Knowledge\Embedder;
use App\Knowledge\KnowledgeChunk;
use App\Models\Guide;
use Illuminate\Support\Collection;

/**
 * Keeps the guide catalogue's passages in the knowledge index in step with the guides. A passage
 * is embedded again only when its text or the embedding model changed; a passage the guide no
 * longer has — a stage removed, a guide unpublished — is deleted.
 */
class GuideIndexer
{
    public const SOURCE = 'guide-catalogue';

    public function __construct(private GuideChunker $chunker, private Embedder $embedder) {}

    /**
     * Index one guide. A guide gone, unpublished or never released leaves no passage behind.
     *
     * @return array{embedded: int, kept: int, deleted: int}
     */
    public function index(int $guideId): array
    {
        $guide = Guide::find($guideId);

        return $this->sync(collect($guide ? $this->chunker->for($guide) : []), [$guideId]);
    }

    /**
     * Index every guide, and remove the passages of any guide that no longer qualifies.
     *
     * @return array{embedded: int, kept: int, deleted: int}
     */
    public function rebuild(): array
    {
        $guideIds = array_values(KnowledgeChunk::query()->where('source', self::SOURCE)->distinct()
            ->pluck('guide_id')->map(fn (mixed $id) => (int) $id)->all());

        return $this->sync($this->chunker->all(), $guideIds);
    }

    /**
     * @param  Collection<int, Chunk>  $chunks
     * @param  list<int>  $guideIds  guides whose passages not in `$chunks` are to be deleted
     * @return array{embedded: int, kept: int, deleted: int}
     */
    private function sync(Collection $chunks, array $guideIds): array
    {
        $model = $this->embedder->model();
        $existing = KnowledgeChunk::query()
            ->where('source', self::SOURCE)
            ->whereIn('guide_id', array_unique([...$guideIds, ...$chunks->pluck('guideId')->all()]))
            ->get(['id', 'identity', 'content_hash', 'embedding_model'])
            ->keyBy('identity');

        $stale = $chunks->reject(function (Chunk $chunk) use ($existing, $model) {
            $row = $existing->get($chunk->identity());

            return $row && $row->content_hash === $this->hash($chunk) && $row->embedding_model === $model;
        })->values();

        $vectors = $this->embedder->embed(array_values($stale->map(fn (Chunk $chunk) => $chunk->content)->all()));

        $stale->each(fn (Chunk $chunk, int $index) => KnowledgeChunk::updateOrCreate(
            ['identity' => $chunk->identity()],
            [...$this->attributes($chunk), 'embedding_model' => $model, 'embedding' => $vectors[$index]],
        ));

        // Unchanged text can still move: a guide listed or closed, a stage renumbered.
        $embedded = $stale->map(fn (Chunk $chunk) => $chunk->identity())->all();
        $chunks->reject(fn (Chunk $chunk) => in_array($chunk->identity(), $embedded, true))
            ->each(fn (Chunk $chunk) => KnowledgeChunk::query()->where('identity', $chunk->identity())->update($this->attributes($chunk)));

        $kept = $chunks->map(fn (Chunk $chunk) => $chunk->identity())->all();
        $gone = $existing->keys()->diff($kept)->all();
        KnowledgeChunk::query()->whereIn('identity', $gone)->delete();

        return ['embedded' => $stale->count(), 'kept' => $chunks->count() - $stale->count(), 'deleted' => count($gone)];
    }

    /** @return array<string, mixed> */
    private function attributes(Chunk $chunk): array
    {
        return [
            'source' => self::SOURCE,
            'guide_id' => $chunk->guideId,
            'guide_title' => $chunk->guideTitle,
            'version_id' => $chunk->versionId,
            'locale' => $chunk->locale,
            'chunk_type' => $chunk->chunkType,
            'stage_key' => $chunk->stageKey,
            'stage_position' => $chunk->stagePosition,
            'listed' => $chunk->listed,
            'content' => $chunk->content,
            'content_hash' => $this->hash($chunk),
        ];
    }

    private function hash(Chunk $chunk): string
    {
        return hash('sha256', $chunk->content);
    }
}
