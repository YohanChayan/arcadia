<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One browser an account reads Arcadia on. Known by the hash of the value in its `arcadia_device`
 * cookie, never the value itself. See `App\Services\Devices` for the rules.
 */
class Device extends Model
{
    /** Unused this long, a device stops counting toward the limit, and is forgotten. */
    public const IDLE_DAYS = 90;

    protected $table = 'guide_devices';

    protected $fillable = ['user_id', 'token_hash', 'label', 'last_used_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Used within the idle window: the devices that hold a slot. */
    public function scopeActive(Builder $query): void
    {
        $query->where('last_used_at', '>=', now()->subDays(self::IDLE_DAYS));
    }
}
