<?php

namespace App\Actions\Readers;

use App\Models\Reader;
use Illuminate\Database\Eloquent\Builder;

/**
 * One name per person: what a reader is called is the same on every guide of this language
 * given to their email, so "My guides", each guide's greeting and each invitation agree. Naming
 * them on one guide names them on all of them, removed ones included, so a reader added back
 * keeps it; a blank name goes back to their account's first name everywhere (`displayName()`).
 *
 * Another language's guides keep their own: each language's editor names its own readers.
 */
class NameReader
{
    public function handle(Reader $reader, ?string $name): void
    {
        $this->assignmentsOf($reader)->update(['name' => filled($name) ? trim($name) : null]);
    }

    /** The name this person already has on another guide of this language, if any. */
    public function knownName(Reader $reader): ?string
    {
        return $this->assignmentsOf($reader)->whereNotNull('name')->latest('updated_at')->value('name');
    }

    /** @return Builder<Reader> every assignment of this reader's email in their guide's language */
    private function assignmentsOf(Reader $reader): Builder
    {
        $locale = $reader->guide()->value('locale');

        return Reader::withTrashed()
            ->where('email', $reader->email)
            ->whereHas('guide', fn (Builder $guides) => $guides->where('locale', $locale));
    }
}
