<?php

namespace App\Models;

use App\Enums\ActivityFamily;
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
        // Not fillable: only the additive import writes it (`ActivityFamily`).
        'activity' => 'array',
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

    /**
     * Roughly how long the line takes, for a locked stage's preview and "My guides": the clip's length when it has
     * an end, else the minutes its brief gives (`6 min`, `8 min de lectura`, `3:33`), else null.
     * Only the number leaves the brief, which is never sent to a reader. Never `hint`, which is
     * free text, and never the step length, which is the research's target, not the line's.
     */
    /**
     * Its quick check, when it carries a valid one (`ActivityFamily`): answers included, for the
     * server to check a reader's against. Null on any other line.
     *
     * @return array{family: string, questions: list<array<string, mixed>>}|null
     */
    public function quickCheck(): ?array
    {
        return (ActivityFamily::forPage($this->activity, $this, withAnswers: true)['family'] ?? null) === ActivityFamily::QUICK_CHECK->value
            ? $this->activity
            : null;
    }

    public function minutes(): ?int
    {
        if ($this->clip_start !== null && $this->clip_end !== null) {
            return max(1, (int) ceil(($this->clip_end - $this->clip_start) / 60));
        }

        $duration = (string) data_get($this->brief, 'duration');

        if (preg_match('/^\s*(\d+):(\d{2})\b/', $duration, $clock)) {
            return max(1, (int) ceil(((int) $clock[1] * 60 + (int) $clock[2]) / 60));
        }

        if (preg_match('/^\s*(\d+)\s*min/i', $duration, $count)) {
            return max(1, (int) $count[1]);
        }

        return null;
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
