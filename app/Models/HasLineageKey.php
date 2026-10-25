<?php

namespace App\Models;

use Illuminate\Support\Str;
use LogicException;

/**
 * A `key` that names a node across versions — the same line, stage, card or section in every
 * copy of the guide.
 *
 * Minted here when the node is first written, never fillable, copied unchanged by
 * `DraftNextVersion`, and it refuses to change. For an item it is what a reader's progress is
 * stored against; for every node it is what `VersionDiff` matches on, so a renamed stage reads as
 * renamed rather than as one removed and another added.
 */
trait HasLineageKey
{
    protected static function bootHasLineageKey(): void
    {
        static::creating(function (self $node) {
            $node->key ??= self::newLineageKey();
        });

        static::updating(function (self $node) {
            if ($node->isDirty('key')) {
                throw new LogicException('A guide node\'s key never changes: it names the node in every version.');
            }
        });
    }

    /** Never used by any node of its kind, in any guide or version. */
    private static function newLineageKey(): string
    {
        do {
            $key = Str::lower(Str::random(12));
        } while (self::where('key', $key)->exists());

        return $key;
    }
}
