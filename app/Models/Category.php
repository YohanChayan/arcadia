<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Where a guide sits — Office, Business, Crypto — one vocabulary per locale.
 *
 * Its slug is derived from its name on every save, never typed, so the two cannot disagree.
 */
class Category extends Model
{
    protected $table = 'guide_categories';

    protected $fillable = ['locale', 'name'];

    protected static function booted(): void
    {
        static::saving(fn (self $term) => $term->slug = Str::slug($term->name));
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }
}
