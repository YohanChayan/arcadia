<?php

namespace App\Assistant\Agents;

use App\Assistant\Models\Turn;
use App\Assistant\Prompts;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The second of a turn's two calls: the reply the visitor reads, streamed. Its instructions are
 * the static part of the master prompt — persona, sales method, disclosure rules — and never
 * change within a release, so they and the history form a stable prefix a provider can cache.
 * Everything that changes per turn travels with the visitor's message instead.
 */
class Speaker implements Agent, Conversational
{
    use Promptable;
    use ShowsHistory;

    /**
     * @param  Collection<int, Turn>  $history
     */
    public function __construct(
        private string $locale,
        private Collection $history,
    ) {}

    public function instructions(): Stringable|string
    {
        return app(Prompts::class)->speakerInstructions($this->locale);
    }

    public function provider(): string
    {
        return config('assistant.provider');
    }

    public function model(): string
    {
        return config('assistant.models.speaker');
    }
}
