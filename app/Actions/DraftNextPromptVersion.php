<?php

namespace App\Actions;

use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use Illuminate\Support\Facades\DB;

/**
 * The next version of a prompt template: a copy of the latest one, to change freely. Asked for
 * whenever the owner wants to keep what is there and work on a copy — and the only way on once
 * the latest is frozen.
 */
class DraftNextPromptVersion
{
    public function handle(PromptTemplate $template): PromptVersion
    {
        $latest = $template->latestVersion();

        return DB::transaction(function () use ($template, $latest) {
            $next = $template->versions()->create(['number' => $latest->number + 1]);

            foreach ($latest->sections as $section) {
                $next->sections()->save($section->replicate()->setRelations([]));
            }

            foreach ($latest->variables as $variable) {
                $next->variables()->save($variable->replicate()->setRelations([]));
            }

            return $next;
        });
    }
}
