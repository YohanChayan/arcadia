<?php

namespace App\Actions;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a guide's description — what it is, not what is in it: title, level,
 * category, status, the greeting its reader sees first, its goals — its own lines of text — the
 * topics it is filed under and the motivation phrases it draws on. Its content lives in its versions and is edited there.
 *
 * The locale is the site's, given by the caller, and fixed once the guide exists: a Spanish
 * guide does not become an English one; the English one is a separate guide, its counterpart.
 */
class SaveGuide
{
    /**
     * @param  list<int>  $topicIds  of this locale's topics
     * @param  list<string>  $goals  its own lines, tidied by `Guide::goalLines()`
     */
    public function handle(
        ?Guide $guide,
        string $locale,
        string $title,
        Level $level,
        ?int $categoryId,
        GuideStatus $status,
        array $topicIds,
        array $goals,
        ?string $greeting = null,
        ?array $phraseIds = null,
    ): Guide {
        return DB::transaction(function () use ($guide, $locale, $title, $level, $categoryId, $status, $topicIds, $goals, $greeting, $phraseIds) {
            $guide ??= new Guide(['locale' => $locale]);

            $guide->fill([
                'title' => $title,
                'level' => $level,
                'category_id' => $categoryId,
                'status' => $status,
                'greeting' => $greeting,
                'goals' => Guide::goalLines($goals) ?: null,
            ])->save();

            $guide->topics()->sync($topicIds);
            // Null leaves the choice as it was: a caller that does not offer it does not clear it.
            if ($phraseIds !== null) {
                $guide->phrases()->sync($phraseIds);
            }

            return $guide;
        });
    }
}
