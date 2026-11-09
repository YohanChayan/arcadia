<?php

namespace App\Providers;

use App\Knowledge\Contracts\Retriever;
use App\Knowledge\Retrievers\FakeRetriever;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the knowledge index's contract. Until the guide-catalogue retriever exists, callers get
 * canned passages, so code that depends on the contract can be built and tested now.
 */
class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Retriever::class, FakeRetriever::class);
    }
}
