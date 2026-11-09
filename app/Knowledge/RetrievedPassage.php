<?php

namespace App\Knowledge;

/**
 * One passage the index returned. `content` is promise-level text, ready to place in a prompt;
 * `score` runs from 0 to 1, higher meaning closer to the query; `source` names the retriever
 * that found it.
 */
final readonly class RetrievedPassage
{
    /**
     * @param  array{stagePosition?: int}  $metadata
     */
    public function __construct(
        public string $content,
        public float $score,
        public string $source,
        public int $guideId,
        public string $guideTitle,
        public ChunkType $chunkType,
        public string $locale,
        public array $metadata = [],
    ) {}
}
