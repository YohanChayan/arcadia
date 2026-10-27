<?php

namespace App\Models;

use App\Enums\SectionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prose block outside the stages: how to use the guide, where to go further, tips.
 */
class Section extends Model
{
    use FrozenOnceReleased;
    use HasLineageKey;

    protected $table = 'guide_sections';

    protected $fillable = ['kind', 'title', 'body', 'position'];

    protected $casts = [
        'kind' => SectionKind::class,
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
}
