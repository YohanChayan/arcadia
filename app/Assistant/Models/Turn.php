<?php

namespace App\Assistant\Models;

use App\Assistant\TurnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One visitor message and the assistant's reply, with what the turn decided and cost. See the
 * `assistant_turns` migration.
 */
class Turn extends Model
{
    use HasUlids;

    protected $table = 'assistant_turns';

    protected $fillable = [
        'status', 'visitor_message', 'reply', 'engagement', 'offered_guide_id',
        'passages', 'usage', 'cost', 'model',
    ];

    protected $casts = [
        'status' => TurnStatus::class,
        'engagement' => 'integer',
        'offered_guide_id' => 'integer',
        'passages' => 'array',
        'usage' => 'array',
        'cost' => 'float',
    ];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
