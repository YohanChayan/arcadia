<?php

namespace App\Knowledge\Retrievers;

use App\Knowledge\Contracts\Retriever;
use App\Knowledge\Embedder;
use App\Knowledge\GuideCatalogue\GuideIndexer;
use App\Knowledge\KnowledgeChunk;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;

/**
 * The guide catalogue's passages closest to a question, from the knowledge index: the question is
 * embedded with the same model as the passages, then matched by cosine distance in pgvector,
 * filtered in SQL by locale and — unless asked otherwise — by whether the guide is listed.
 */
class GuideCatalogueRetriever implements Retriever
{
    public function __construct(private Embedder $embedder) {}

    public function retrieve(RetrievalQuery $query): RetrievedPassages
    {
        if (trim($query->text) === '' || $query->limit < 1) {
            return new RetrievedPassages;
        }

        $vector = $this->embedder->embed([$query->text])[0];

        $rows = KnowledgeChunk::query()
            ->where('source', GuideIndexer::SOURCE)
            ->where('locale', $query->locale)
            ->where('embedding_model', $this->embedder->model())
            ->when($query->listedOnly, fn ($chunks) => $chunks->where('listed', true))
            ->select(['guide_id', 'guide_title', 'locale', 'chunk_type', 'stage_position', 'content'])
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($query->limit)
            ->get();

        return new RetrievedPassages($rows->map(fn (KnowledgeChunk $chunk) => new RetrievedPassage(
            content: $chunk->content,
            score: $this->score((float) $chunk->getAttribute('distance')),
            source: GuideIndexer::SOURCE,
            guideId: $chunk->guide_id,
            guideTitle: $chunk->guide_title,
            chunkType: $chunk->chunk_type,
            locale: $chunk->locale,
            metadata: $chunk->stage_position !== null ? ['stagePosition' => $chunk->stage_position] : [],
        ))->values()->all());
    }

    /** Cosine distance runs 0–2; the contract's score runs 0–1, higher meaning closer. */
    private function score(float $distance): float
    {
        return round(max(0.0, min(1.0, 1 - $distance)), 4);
    }
}
