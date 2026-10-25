<?php

namespace App\Models;

use App\Enums\PhraseMoment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A short motivation phrase, for one moment, in one language. `{name}` in it becomes the reader's
 * name, or is left out when there is none. See the `create_guide_phrases_table` migration.
 */
class Phrase extends Model
{
    public const MAX_LENGTH = 120;

    protected $table = 'guide_phrases';

    protected $fillable = ['locale', 'moment', 'text'];

    protected $casts = [
        'moment' => PhraseMoment::class,
    ];

    public function guides(): BelongsToMany
    {
        return $this->belongsToMany(Guide::class, 'guide_phrase_guide', 'phrase_id', 'guide_id');
    }

    /** For this reader: `{name}` filled, or taken out and the sentence closed up around it. */
    public function forReader(?string $name): string
    {
        $text = $name
            ? str_ireplace('{name}', $name, $this->text)
            : preg_replace(['/,?\s*\{name\}\s*/i', '/\s+([,.!?])/'], [' ', '$1'], $this->text);

        return trim(preg_replace('/\s{2,}/', ' ', $text), ' ,');
    }
}
