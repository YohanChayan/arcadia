<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One full copy of a guide. Version 1 is the base, created with the guide; each later one is
 * drafted as a copy of the latest (`DraftNextVersion`), edited freely, then released — and from
 * then on frozen (`FrozenOnceReleased`), so a buyer keeps what they were given.
 */
class Version extends Model
{
    protected $table = 'guide_versions';

    protected $fillable = ['number'];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    /** How deep inside `whileFixing()` the current code is. */
    private static int $fixing = 0;

    /**
     * Run a repair that must reach released versions too. Only `FixItemEverywhere` should need
     * this: it is the single exception to a released version being frozen.
     *
     * @template T
     *
     * @param  callable(): T  $repair
     * @return T
     */
    public static function whileFixing(callable $repair): mixed
    {
        self::$fixing++;

        try {
            return $repair();
        } finally {
            self::$fixing--;
        }
    }

    public static function isFixing(): bool
    {
        return self::$fixing > 0;
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    public function release(): void
    {
        $this->forceFill(['released_at' => now()])->save();
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(VersionChange::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
