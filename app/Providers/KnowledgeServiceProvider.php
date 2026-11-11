<?php

namespace App\Providers;

use App\Jobs\IndexGuideJob;
use App\Knowledge\Contracts\Retriever;
use App\Knowledge\Retrievers\CompositeRetriever;
use App\Knowledge\Retrievers\FakeRetriever;
use App\Knowledge\Retrievers\GuideCatalogueRetriever;
use App\Models\Guide;
use App\Models\Version;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the knowledge index's contract, and keeps the index in step with the guides.
 *
 * Callers get every corpus behind one `CompositeRetriever` — today only the guide catalogue — or,
 * with `knowledge.retriever` set to `fake`, canned passages: in the tests, and wherever there is
 * no Gemini key or no index yet.
 *
 * The index listens to the guides; the guides know nothing of it. A released version, or a change
 * to what a guide promises or whether it is offered, queues that guide's reindexing — after the
 * transaction commits, so the job reads what was saved. `knowledge.index_on_change` turns it off.
 */
class KnowledgeServiceProvider extends ServiceProvider
{
    /** The guide's own fields that reach a passage, or decide whether it is indexed and listed. */
    private const PROMISE = ['title', 'level', 'category_id', 'goals', 'pitch', 'status', 'open_for_enrollment'];

    public function register(): void
    {
        $this->app->bind(Retriever::class, fn ($app) => config('knowledge.retriever') === 'fake'
            ? $app->make(FakeRetriever::class)
            : new CompositeRetriever([$app->make(GuideCatalogueRetriever::class)]));
    }

    public function boot(): void
    {
        Version::updated(function (Version $version) {
            if ($version->wasChanged('released_at') && $version->isReleased()) {
                $this->reindex($version->guide_id);
            }
        });

        Guide::updated(function (Guide $guide) {
            if ($guide->wasChanged(self::PROMISE)) {
                $this->reindex($guide->id);
            }
        });

        Guide::deleted(fn (Guide $guide) => $this->reindex($guide->id));
    }

    private function reindex(int $guideId): void
    {
        if (config('knowledge.index_on_change')) {
            IndexGuideJob::dispatch($guideId)->afterCommit();
        }
    }
}
