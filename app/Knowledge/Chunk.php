<?php

namespace App\Knowledge;

/**
 * One passage to embed, before it has a vector. `identity` names it across versions — the guide,
 * and for a stage its lineage key — so a new release replaces the passage instead of adding one.
 */
final readonly class Chunk
{
    public function __construct(
        public int $guideId,
        public string $guideTitle,
        public int $versionId,
        public string $locale,
        public ChunkType $chunkType,
        public string $content,
        public bool $listed,
        public ?string $stageKey = null,
        public ?int $stagePosition = null,
    ) {}

    public function identity(): string
    {
        return $this->chunkType === ChunkType::STAGE
            ? "guide:{$this->guideId}:stage:{$this->stageKey}"
            : "guide:{$this->guideId}";
    }
}
