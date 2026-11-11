<?php

namespace App\Knowledge\Retrievers;

use App\Knowledge\Contracts\Retriever;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;

/**
 * Asks every corpus the same question and keeps the closest passages overall. A new corpus is one
 * more retriever here; callers see the same contract.
 */
class CompositeRetriever implements Retriever
{
    /** @param  list<Retriever>  $retrievers */
    public function __construct(private array $retrievers) {}

    public function retrieve(RetrievalQuery $query): RetrievedPassages
    {
        return (new RetrievedPassages)
            ->merge(collect($this->retrievers)->flatMap(fn (Retriever $retriever) => $retriever->retrieve($query)->all()))
            ->sortByDesc(fn (RetrievedPassage $passage) => $passage->score)
            ->take($query->limit)
            ->values();
    }
}
