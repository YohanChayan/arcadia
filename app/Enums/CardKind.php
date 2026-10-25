<?php

namespace App\Enums;

/**
 * A card is one creator's material, or the final challenge. The challenge is a card inside the
 * last stage, not a stage of its own, so its tasks count toward that stage's progress.
 */
enum CardKind: string
{
    case SOURCE = 'source';
    case CHALLENGE = 'challenge';
}
