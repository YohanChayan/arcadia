<?php

namespace App\Services;

use App\Enums\PhraseMoment;
use App\Models\Guide;
use App\Models\Phrase;
use Illuminate\Support\Collection;

/**
 * Which motivation phrase a reader sees: one at random among those for the moment — from the
 * guide's own choice when the owner made one for that moment, else from every phrase of the
 * language. A different one on each visit, so they stay fresh; none when there is none to show.
 *
 * Loads a language's phrases once, so a dashboard of several guides asks the database once.
 */
class MotivationPicker
{
    /** @var array<string, Collection<int, Phrase>> */
    private array $byLocale = [];

    public function welcome(string $locale, ?string $name): ?string
    {
        return $this->pick($this->all($locale)->where('moment', PhraseMoment::WELCOME), $name);
    }

    /** For a guide's card: where the reader stands on it. Its `phrases` should be loaded. */
    public function forGuide(Guide $guide, PhraseMoment $moment, ?string $name): ?string
    {
        return $this->pick($this->candidates($guide, $moment), $name);
    }

    /**
     * Every phrase a guide may show at a moment, worded for the reader: for the page to pick
     * from when the moment comes while it is open, as a stage completed by a tick.
     *
     * @return list<string>
     */
    public function allForGuide(Guide $guide, PhraseMoment $moment, ?string $name): array
    {
        return $this->candidates($guide, $moment)->map(fn (Phrase $phrase) => $phrase->forReader($name))->values()->all();
    }

    /** @return Collection<int, Phrase> the guide's own choice for the moment, else every one of its language */
    private function candidates(Guide $guide, PhraseMoment $moment): Collection
    {
        $chosen = $guide->phrases->where('moment', $moment);

        return $chosen->isNotEmpty() ? $chosen : $this->all($guide->locale)->where('moment', $moment);
    }

    /** @param  Collection<int, Phrase>  $phrases */
    private function pick(Collection $phrases, ?string $name): ?string
    {
        return $phrases->isEmpty() ? null : $phrases->random()->forReader($name);
    }

    /** @return Collection<int, Phrase> */
    private function all(string $locale): Collection
    {
        return $this->byLocale[$locale] ??= Phrase::query()->where('locale', $locale)->get();
    }
}
