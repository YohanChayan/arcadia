<?php

namespace App\Models;

use LogicException;

/**
 * The parts of a used prompt version — its sections and variables — cannot be created, edited
 * or deleted: a request or a guide must keep pointing at exactly the prompt it got. A change is
 * a new version (`DraftNextPromptVersion`).
 */
trait FrozenOnceUsed
{
    protected static function bootFrozenOnceUsed(): void
    {
        foreach (['creating', 'updating', 'deleting'] as $event) {
            static::$event(function (self $part) {
                if ($part->version?->isFrozen()) {
                    throw new LogicException('A prompt version that has been used is frozen: draft a new version.');
                }
            });
        }
    }
}
