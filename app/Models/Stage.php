<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One step of a guide: a title, what the reader can do when they finish it, and its cards.
 */
class Stage extends Model
{
    use FrozenOnceReleased;
    use HasLineageKey;

    protected $table = 'guide_stages';

    protected $fillable = ['title', 'goal', 'position'];

    protected $casts = [
        // Not fillable: only the additive import writes it (`ActivityFamily`).
        'activity' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function owningVersion(): ?Version
    {
        return $this->version;
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
