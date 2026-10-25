<?php

namespace App\Models;

use App\Enums\PromptVariableType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One declared blank of a prompt version: the `{{name}}` its sections use, how the form asks
 * for it, whether the prompt can be copied without it, and what its field starts filled with
 * (`default_value`; a `price` with none starts at the host's price — `PromptFormOptions`).
 */
class PromptVariable extends Model
{
    use FrozenOnceUsed;

    protected $table = 'guide_prompt_variables';

    protected $fillable = ['name', 'label', 'type', 'required', 'default_value', 'position'];

    protected $casts = [
        'type' => PromptVariableType::class,
        'required' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'version_id');
    }
}
