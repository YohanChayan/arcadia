<?php

namespace App\Assistant;

use App\Knowledge\RetrievedPassage;
use App\Knowledge\RetrievedPassages;
use Laravel\Ai\Responses\Data\TextUsage;

/**
 * What one completed turn cost and decided, as a channel reports it. `toArray()` is the shape the
 * web chat's `done` event carries (`resources/js/types/assistant.ts`, `AssistantTurnMetrics`).
 */
final readonly class TurnMetrics
{
    /**
     * @param  array{guide_id: int, guide_title: string}|null  $conversion
     */
    public function __construct(
        public string $turnId,
        public RetrievedPassages $passages,
        public int $engagement,
        public TextUsage $usage,
        public ?float $cost,
        public ?array $conversion,
    ) {}

    /**
     * @return array{
     *     turn_id: string,
     *     passages: list<array{guide_id: int, guide_title: string, source: string, score: float, content: string}>,
     *     engagement: int,
     *     usage: array{input_tokens: int, output_tokens: int, cached_input_tokens: int|null},
     *     cost: float|null,
     *     conversion: array{guide_id: int, guide_title: string}|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'turn_id' => $this->turnId,
            'passages' => self::passagesToArray($this->passages),
            'engagement' => $this->engagement,
            'usage' => [
                'input_tokens' => $this->usage->inputTokens,
                'output_tokens' => $this->usage->outputTokens,
                'cached_input_tokens' => $this->usage->cacheReadInputTokens,
            ],
            'cost' => $this->cost,
            'conversion' => $this->conversion,
        ];
    }

    /**
     * The passages as a turn stores and reports them.
     *
     * @return list<array{guide_id: int, guide_title: string, source: string, score: float, content: string}>
     */
    public static function passagesToArray(RetrievedPassages $passages): array
    {
        return array_values($passages->toBase()->map(fn (RetrievedPassage $passage): array => [
            'guide_id' => $passage->guideId,
            'guide_title' => $passage->guideTitle,
            'source' => $passage->source,
            'score' => $passage->score,
            'content' => $passage->content,
        ])->all());
    }
}
