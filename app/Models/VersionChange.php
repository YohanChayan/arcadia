<?php

namespace App\Models;

use App\Enums\ChangeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a version's change log — a new exam, a removed video. It describes the change; the
 * content itself is the version's copy.
 */
class VersionChange extends Model
{
    protected $table = 'guide_version_changes';

    protected $fillable = ['kind', 'title', 'notes'];

    protected $casts = [
        'kind' => ChangeKind::class,
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }
}
