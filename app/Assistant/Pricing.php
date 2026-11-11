<?php

namespace App\Assistant;

use Laravel\Ai\Responses\Data\TextUsage;

/**
 * What a call cost, from `assistant.prices` — `laravel/ai` reports tokens but no prices. Cached
 * input is billed at its own rate; a model with no price configured has no cost, rather than a
 * cost of zero.
 */
class Pricing
{
    public function cost(string $model, TextUsage $usage): ?float
    {
        // Indexed, not dotted: a model's name has dots of its own (`gemini-3.1-flash-lite`).
        /** @var array{input: float, cached_input: float, output: float}|null $price */
        $price = config('assistant.prices')[$model] ?? null;

        if ($price === null) {
            return null;
        }

        return ($usage->uncachedInputTokens() * $price['input']
            + ($usage->cacheReadInputTokens ?? 0) * $price['cached_input']
            + $usage->outputTokens * $price['output']) / 1_000_000;
    }
}
