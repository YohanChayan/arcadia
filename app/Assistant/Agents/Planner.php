<?php

namespace App\Assistant\Agents;

use App\Assistant\Models\Turn;
use App\Assistant\Prompts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The first of a turn's two calls: reads the visitor's latest message against the conversation
 * and this turn's passages, and answers in structure — how engaged the visitor is, whether they
 * accepted a guide already offered, and which guide the reply should offer. It never speaks to
 * the visitor; `Speaker` does, told what was decided here.
 */
class Planner implements Agent, Conversational, HasStructuredOutput
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
        return app(Prompts::class)->plannerInstructions($this->locale);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'engagement' => $schema->integer()->min(0)->max(100)->required(),
            'accepted_guide_id' => $schema->integer()->nullable()->required(),
            'offer_guide_id' => $schema->integer()->nullable()->required(),
        ];
    }

    public function provider(): string
    {
        return config('assistant.provider');
    }

    public function model(): string
    {
        return config('assistant.models.planner');
    }
}
