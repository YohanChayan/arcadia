<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line a reader ticked as done, on their account — written and cleared through
 * `Reader::tick()`, read with `Reader::tickedKeys()`.
 */
class Tick extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'guide_ticks';

    protected $fillable = [
        'user_id',
        'guide_id',
        'item_key',
    ];
}
