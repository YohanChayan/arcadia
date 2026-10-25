<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in account asking to enroll in a guide that is not shared with it — once per guide,
 * with an optional message (ADR 0022). The owner arranges payment with them outside Arcadia, then
 * approves it on the guide's Readers screen, which assigns the account's email, or declines it.
 * Answered, it is kept: a decline stands, and the editor counts both. `owner_note` is his alone.
 */
class AccessRequest extends Model
{
    /** A message, not a letter. */
    public const MAX_MESSAGE = 1000;

    protected $table = 'guide_access_requests';

    protected $fillable = [
        'guide_id',
        'user_id',
        'message',
    ];

    protected $casts = [
        'status' => AccessRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', AccessRequestStatus::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === AccessRequestStatus::PENDING;
    }

    /** Answered: who did, and when. */
    public function decide(AccessRequestStatus $status, User $by): void
    {
        $this->forceFill(['status' => $status, 'decided_at' => now(), 'decided_by' => $by->id])->save();
    }
}
