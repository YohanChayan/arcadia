<?php

namespace App\Knowledge\Contracts;

use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassages;

/**
 * The one way out of the knowledge index. Each corpus — the guide catalogue today, others later —
 * is a retriever; callers depend on this contract and never on a corpus, so a new one is added
 * without touching them.
 */
interface Retriever
{
    public function retrieve(RetrievalQuery $query): RetrievedPassages;
}
