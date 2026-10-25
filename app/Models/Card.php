<?php

namespace App\Models;

use App\Enums\CardKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One creator's material within a stage — who made it, why it is here, an optional quote and
 * closing note — or the stage's final challenge.
 */
class Card extends Model
{
    use FrozenOnceReleased;
    use HasLineageKey;

    protected $table = 'guide_cards';

    protected $fillable = ['kind', 'byline', 'description', 'quote', 'closing_note', 'position'];

    protected $casts = [
        'kind' => CardKind::class,
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Card $card) {
            $card->version_id ??= $card->stage->version_id;
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function owningVersion(): ?Version
    {
        return $this->version ?? $this->stage?->version;
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('position');
    }
}
