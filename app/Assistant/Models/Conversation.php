<?php

namespace App\Assistant\Models;

use App\Assistant\TurnStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One conversation with the sales assistant, on one channel and in one language. See the
 * `assistant_conversations` migration for who `user_id` and `participant` are.
 */
class Conversation extends Model
{
    use HasUlids;

    protected $table = 'assistant_conversations';

    protected $fillable = ['channel', 'locale', 'user_id', 'participant'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Turn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Conversion, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    /**
     * The turns the model is shown as history: completed ones, the latest `$limit`, oldest first.
     *
     * @return Collection<int, Turn>
     */
    public function history(int $limit): Collection
    {
        return $this->turns()
            ->where('status', TurnStatus::DONE)
            ->reorder('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * The guides the assistant has offered so far, by id — the only ones a visitor can accept.
     *
     * @return array<int, int>
     */
    public function offeredGuideIds(): array
    {
        return $this->turns()
            ->where('status', TurnStatus::DONE)
            ->whereNotNull('offered_guide_id')
            ->pluck('offered_guide_id')
            // `pluck()` skips the model's casts.
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
