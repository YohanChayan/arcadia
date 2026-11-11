<?php

namespace App\Console\Commands;

use App\Knowledge\GuideCatalogue\GuideIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Brings the whole knowledge index in step with the guides: embeds what is new or changed,
 * deletes what no longer qualifies. Safe to run at any time — unchanged passages cost nothing.
 * For a first deploy, a lost volume or a new embedding model.
 */
#[Signature('knowledge:rebuild')]
#[Description("Embed every published guide's passages into the knowledge index and remove stale ones")]
class RebuildKnowledgeIndex extends Command
{
    public function handle(GuideIndexer $indexer): int
    {
        $counts = $indexer->rebuild();

        $this->components->info("Embedded {$counts['embedded']}, unchanged {$counts['kept']}, deleted {$counts['deleted']}.");

        return self::SUCCESS;
    }
}
