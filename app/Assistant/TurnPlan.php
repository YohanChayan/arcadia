<?php

namespace App\Assistant;

/**
 * What the planner read in the visitor's latest message, already held to the rules: a guide is
 * accepted only if it was offered earlier in the conversation, and offered only if this turn's
 * passages name it — so neither can be a guide the visitor may not be sold.
 */
final readonly class TurnPlan
{
    public function __construct(
        public int $engagement,
        public ?int $acceptedGuideId,
        public ?int $offerGuideId,
    ) {}

    /**
     * @param  array{engagement?: mixed, accepted_guide_id?: mixed, offer_guide_id?: mixed}  $structured
     * @param  array<int, int>  $offeredGuideIds  offered earlier in the conversation
     * @param  array<int, int>  $retrievedGuideIds  named by this turn's passages
     */
    public static function fromPlanner(array $structured, array $offeredGuideIds, array $retrievedGuideIds): self
    {
        $accepted = is_numeric($structured['accepted_guide_id'] ?? null) ? (int) $structured['accepted_guide_id'] : null;
        $offer = is_numeric($structured['offer_guide_id'] ?? null) ? (int) $structured['offer_guide_id'] : null;

        return new self(
            engagement: max(0, min(100, (int) ($structured['engagement'] ?? 0))),
            acceptedGuideId: in_array($accepted, $offeredGuideIds, true) ? $accepted : null,
            offerGuideId: in_array($offer, $retrievedGuideIds, true) ? $offer : null,
        );
    }
}
