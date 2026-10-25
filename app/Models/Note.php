<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reader's own note on a guide: on one line (`item`) or a whole stage (`stage`), by that node's
 * lineage key, so it survives a new version as a tick does. See the `create_guide_notes_table`
 * migration.
 */
class Note extends Model
{
    public const ITEM = 'item';

    public const STAGE = 'stage';

    /** Long enough for a real note; short enough to stay a note. */
    public const MAX_LENGTH = 2000;

    protected $table = 'guide_notes';

    protected $fillable = ['user_id', 'guide_id', 'target_kind', 'target_key', 'body'];

    /** What the guide page needs of a note. */
    public function toReader(): array
    {
        return $this->only(['id', 'target_kind', 'target_key', 'body', 'updated_at']);
    }
}
