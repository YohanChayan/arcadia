<?php

namespace App\Models;

use LogicException;

/**
 * Content of a released version cannot be created, edited or deleted: a buyer keeps exactly the
 * guide they were given. A change is a new version (`DraftNextVersion`).
 *
 * The one exception is repairing a dead item, which must reach every version that carries it —
 * `FixItemEverywhere`, which runs inside `Version::whileFixing()`.
 */
trait FrozenOnceReleased
{
    abstract public function owningVersion(): ?Version;

    protected static function bootFrozenOnceReleased(): void
    {
        foreach (['creating', 'updating', 'deleting'] as $event) {
            static::$event(function (self $content) {
                if (! Version::isFixing() && $content->owningVersion()?->isReleased()) {
                    throw new LogicException('A released guide version is frozen: draft the next version, or fix the item everywhere.');
                }
            });
        }
    }
}
