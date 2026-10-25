<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AuthProvider extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'alias',
        'icon',
    ];

    protected $appends = ['title'];

    public function title(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => Str::title($attributes['alias']),
        );
    }

    public function userAuthProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class, 'provider_id');
    }
}
