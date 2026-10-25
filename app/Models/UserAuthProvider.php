<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAuthProvider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'provider_id',
        'provider_id_from_oauth',
        'token',
        'refresh_token',
        'expires_at',
        'avatar',
        'nickname',
        'scopes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AuthProvider::class, 'provider_id');
    }

    public function scopeForProvider(Builder $query, int $providerId, string $providerIdFromOauth): Builder
    {
        return $query->where('provider_id', $providerId)
            ->where('provider_id_from_oauth', $providerIdFromOauth);
    }
}
