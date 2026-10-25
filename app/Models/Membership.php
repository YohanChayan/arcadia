<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's place on Arcadia (`User::membership()`): their role, the account's own device limit
 * and its last self-service swap.
 */
class Membership extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'device_limit',
        'devices_replaced_at',
    ];

    protected $casts = [
        'device_limit' => 'integer',
        'devices_replaced_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
