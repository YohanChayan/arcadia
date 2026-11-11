<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Knowledge\GuideCatalogue\GuideIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Brings one guide's passages in the knowledge index up to date, on the knowledge queue so an
 * embedding call never holds up mail. By id, not by model: a guide deleted meanwhile is indexed
 * as gone. One per guide at a time — several edits in a row index once.
 */
class IndexGuideJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $guideId)
    {
        $this->onQueue(QueueName::KNOWLEDGE->value);
    }

    public function uniqueId(): string
    {
        return (string) $this->guideId;
    }

    public function handle(GuideIndexer $indexer): void
    {
        $indexer->index($this->guideId);
    }
}
