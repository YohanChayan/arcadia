<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reader's own answer to an activity with no right one — a flip card `known` or `not_yet` — on
 * their account, by the target's key. See the `create_guide_activity_states_table` migration.
 */
class ActivityState extends Model
{
    public const KNOWN = 'known';

    public const NOT_YET = 'not_yet';

    protected $table = 'guide_activity_states';

    protected $fillable = ['user_id', 'guide_id', 'family', 'target_key', 'state'];
}
