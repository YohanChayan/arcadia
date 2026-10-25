<?php

namespace App\Enums;

/**
 * How far a guide takes its reader. A fixed scale, labelled from `lang/` — not a vocabulary the
 * owner edits, which is what categories and topics are for.
 */
enum Level: string
{
    case BASIC = 'basic';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';

    public function label(): string
    {
        return __("arcadia.levels.{$this->value}");
    }
}
