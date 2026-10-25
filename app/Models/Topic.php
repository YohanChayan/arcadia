<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * What a guide covers — formulas, pivot tables — one vocabulary per locale, many per guide.
 *
 * Its slug is derived from its name on every save, never typed, so the two cannot disagree.
 */
class Topic extends Model
{
    protected $table = 'guide_topics';

    protected $fillable = ['locale', 'name'];

    protected static function booted(): void
    {
        static::saving(fn (self $term) => $term->slug = Str::slug($term->name));
    }

    public function guides(): BelongsToMany
    {
        return $this->belongsToMany(Guide::class, 'guide_topic', 'topic_id', 'guide_id');
    }
}
