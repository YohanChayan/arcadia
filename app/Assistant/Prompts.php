<?php

namespace App\Assistant;

use App\Knowledge\RetrievedPassages;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The master prompt, per language, in `resources/views/assistant/{locale}`: each agent's static
 * instructions, and the per-turn context that travels with the visitor's message. A language is
 * added by giving each method its arm — and listing it in `assistant.locales`.
 */
class Prompts
{
    public function plannerInstructions(string $locale): string
    {
        return view(match ($locale) {
            'es' => 'assistant.es.planner',
            default => throw new InvalidArgumentException("The assistant has no prompts in [{$locale}]."),
        })->render();
    }

    public function speakerInstructions(string $locale): string
    {
        return view(match ($locale) {
            'es' => 'assistant.es.speaker',
            default => throw new InvalidArgumentException("The assistant has no prompts in [{$locale}]."),
        })->render();
    }

    /**
     * @param  Collection<int, string>  $offeredGuides  titles, by guide id
     */
    public function plannerTurn(
        string $locale,
        string $channel,
        ?int $previousEngagement,
        Collection $offeredGuides,
        RetrievedPassages $passages,
        string $message,
    ): string {
        return view(match ($locale) {
            'es' => 'assistant.es.planner-turn',
            default => throw new InvalidArgumentException("The assistant has no prompts in [{$locale}]."),
        }, compact('channel', 'previousEngagement', 'offeredGuides', 'passages', 'message'))->render();
    }

    /**
     * @param  Collection<int, string>  $offeredGuides  titles, by guide id
     */
    public function speakerTurn(
        string $locale,
        string $channel,
        int $engagement,
        Collection $offeredGuides,
        RetrievedPassages $passages,
        ?string $acceptedGuide,
        ?string $offerGuide,
        string $message,
    ): string {
        return view(match ($locale) {
            'es' => 'assistant.es.speaker-turn',
            default => throw new InvalidArgumentException("The assistant has no prompts in [{$locale}]."),
        }, compact('channel', 'engagement', 'offeredGuides', 'passages', 'acceptedGuide', 'offerGuide', 'message'))->render();
    }
}
