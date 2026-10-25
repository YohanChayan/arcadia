<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of a prompt template. Frozen once used — copied for a request, or a guide produced
 * from it — so whatever used it can always say exactly what prompt it got; a change after that
 * is a new version (`DraftNextPromptVersion`).
 *
 * Its `description` — what it is for, which approach it takes — is a label about the version, not
 * part of the prompt, so it stays editable after freezing.
 */
class PromptVersion extends Model
{
    protected $table = 'guide_prompt_versions';

    protected $fillable = ['number', 'description'];

    protected $casts = [
        'frozen_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'template_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PromptSection::class, 'version_id')->orderBy('position');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(PromptVariable::class, 'version_id')->orderBy('position');
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function freeze(): void
    {
        if (! $this->isFrozen()) {
            $this->forceFill(['frozen_at' => now()])->save();
        }
    }
}
