<?php

namespace App\Assistant\Models;

use App\Models\Guide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A visitor accepting a guide the assistant had already offered in that conversation. */
class Conversion extends Model
{
    protected $table = 'assistant_conversions';

    protected $fillable = ['conversation_id', 'turn_id', 'guide_id'];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<Guide, $this> */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }
}
