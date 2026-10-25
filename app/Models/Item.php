<?php

namespace App\Models;

use App\Enums\ItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checkable line of a guide: a video, a playlist, a site, a note or a practice.
 *
 * Its `key` (`HasLineageKey`) is the line's identity **across versions**, and what a reader's
 * progress is stored against on their account (`Tick`). A new key would clear every reader's
 * checkmark for that line without any error — the way the prototype's hand-written ids would
 * have failed. Editing a line keeps its key; a genuinely new line gets a new one.
 */
class Item extends Model
{
    use FrozenOnceReleased;
    use HasLineageKey;

    protected $table = 'guide_items';

    protected $fillable = ['kind', 'title', 'url', 'hint', 'body', 'is_preview', 'is_available', 'position', 'clip_start', 'clip_end', 'clip_evidence'];

    protected $casts = [
        'kind' => ItemKind::class,
        'is_preview' => 'boolean',
        'is_available' => 'boolean',
        // What the line teaches, for the owner: see `BRIEF_FIELDS`. Never sent to a reader.
        'brief' => 'array',
        // The part of a YouTube video the line plays, in seconds from the video's start: see `VideoClip`.
        'clip_start' => 'integer',
        'clip_end' => 'integer',
    ];

    /** A brief's fields, as the research prompt asks for them; anything else an AI adds is dropped. */
    public const BRIEF_FIELDS = ['summary', 'covers', 'level', 'creator', 'duration', 'language', 'search'];

    /** A clip and its evidence. They belong to the video, not the lesson: a repair that swaps the link clears them. */
    public const CLIP_FIELDS = ['clip_start', 'clip_end', 'clip_evidence'];

    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            $item->version_id ??= $item->card->stage->version_id;
        });

        // A clip belongs to its video: a new link that brings no clip of its own plays whole.
        // Otherwise the old video's minutes would play on the new one — in a repair, in every
        // released copy.
        static::updating(function (Item $item) {
            if ($item->isDirty('url') && ! $item->isDirty(self::CLIP_FIELDS)) {
                $item->forceFill(array_fill_keys(self::CLIP_FIELDS, null));
            }
        });
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function owningVersion(): ?Version
    {
        return $this->version ?? $this->card?->stage?->version;
    }
}
