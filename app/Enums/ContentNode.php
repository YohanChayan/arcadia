<?php

namespace App\Enums;

use App\Models\Card;
use App\Models\Item;
use App\Models\Section;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;

/**
 * The four kinds of node in a version's content tree, as one editor addresses them.
 *
 * ```
 * version ─┬─ sections
 *          └─ stages ─ cards ─ items
 * ```
 *
 * Sections and stages hang off the version itself; a card off a stage, an item off a card. One
 * enum, so one controller and two actions serve all four; what differs is answered here.
 */
enum ContentNode: string
{
    case SECTIONS = 'sections';
    case STAGES = 'stages';
    case CARDS = 'cards';
    case ITEMS = 'items';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::SECTIONS => Section::class,
            self::STAGES => Stage::class,
            self::CARDS => Card::class,
            self::ITEMS => Item::class,
        };
    }

    /** The column naming the node's parent — its siblings share it. */
    public function parentColumn(): string
    {
        return match ($this) {
            self::SECTIONS, self::STAGES => 'version_id',
            self::CARDS => 'stage_id',
            self::ITEMS => 'card_id',
        };
    }

    /** The kind of node it hangs off, or null when its parent is the version. */
    public function parent(): ?self
    {
        return match ($this) {
            self::SECTIONS, self::STAGES => null,
            self::CARDS => self::STAGES,
            self::ITEMS => self::CARDS,
        };
    }
}
