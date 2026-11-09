<?php

namespace App\Knowledge\Retrievers;

use App\Knowledge\ChunkType;
use App\Knowledge\Contracts\Retriever;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;

/**
 * Canned passages shaped like the guide catalogue's, for building against the contract before
 * the index exists. It ignores the query's text and returns its passages in the query's locale,
 * up to the limit. The goals are copied from production stages; the titles stand in.
 */
class FakeRetriever implements Retriever
{
    public const SOURCE = 'fake';

    public function retrieve(RetrievalQuery $query): RetrievedPassages
    {
        return RetrievedPassages::make($this->passages($query->locale))->take($query->limit)->values();
    }

    /** @return list<RetrievedPassage> */
    private function passages(string $locale): array
    {
        return [
            $this->passage(5, 'Excel desde cero', ChunkType::STAGE, 'Etapa 1: Arrancar sin miedo. Abres un libro, escribes datos, cambias anchos, das formato de moneda y porcentaje, usas los atajos básicos y sabes por qué tus fórmulas llevan punto y coma.', 0.82, $locale, ['stagePosition' => 1]),
            $this->passage(9, 'Hablar en público', ChunkType::STAGE, 'Etapa 1: Arrancar sin miedo. Saber por qué te trabas al hablar en público y hablar 1 minuto grabado sobre ti aplicando dos de esos arreglos.', 0.74, $locale, ['stagePosition' => 1]),
            $this->passage(91, 'Git sin miedo', ChunkType::STAGE, 'Etapa 2. Lees el historial con git log, sabes qué cambió con git diff y te mueves entre commits sin perderte.', 0.69, $locale, ['stagePosition' => 2]),
            $this->passage(5, 'Excel desde cero', ChunkType::GUIDE, 'Excel desde cero. Nivel básico. Etapas: 1. Arrancar sin miedo.', 0.61, $locale),
        ];
    }

    /** @param  array{stagePosition?: int}  $metadata */
    private function passage(int $guideId, string $guideTitle, ChunkType $chunkType, string $content, float $score, string $locale, array $metadata = []): RetrievedPassage
    {
        return new RetrievedPassage($content, $score, self::SOURCE, $guideId, $guideTitle, $chunkType, $locale, $metadata);
    }
}
