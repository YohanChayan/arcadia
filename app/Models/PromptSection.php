<?php

namespace App\Models;

use App\Enums\PromptSectionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section of a prompt version, its body carrying `{{variable}}` placeholders. Frozen with
 * its version once used (`FrozenOnceUsed`).
 */
class PromptSection extends Model
{
    use FrozenOnceUsed;

    protected $table = 'guide_prompt_sections';

    protected $fillable = ['kind', 'title', 'body', 'position'];

    protected $casts = [
        'kind' => PromptSectionKind::class,
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'version_id');
    }
}
