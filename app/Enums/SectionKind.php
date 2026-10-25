<?php

namespace App\Enums;

/**
 * The guide's prose blocks, outside the stages — the prototype's "how to use", "go further",
 * "what you must master" and "tips". `OTHER` carries its own title.
 */
enum SectionKind: string
{
    case HOW_TO_USE = 'how_to_use';
    case GO_FURTHER = 'go_further';
    case MUST_MASTER = 'must_master';
    case TIPS = 'tips';
    case OTHER = 'other';
}
