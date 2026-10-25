<?php

namespace App\Models;

use App\Enums\Accent;
use App\Enums\Palette;
use App\Services\Appearance;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One person a guide is for, by email. Assignable before they have an account; `user` is that
 * account once one exists with the same address.
 *
 * It carries that reader's own colours, starting from the guide's default when assigned, and
 * who last saved them (`appearance_by`) — the editor's "Me" · "Reader" badge.
 *
 * Their progress is their account's, on this guide (`tick()`, `tickedKeys()`): it survives
 * their removal, and is there again if they are added back.
 *
 * Removing a reader soft-deletes them: their guide then tells them it is no longer available to
 * them, not that it was never shared. `AssignReader` restores the row if they are added back.
 */
class Reader extends Model
{
    use SoftDeletes;

    public const BY_ADMIN = 'admin';

    public const BY_READER = 'reader';

    protected $table = 'guide_readers';

    protected $fillable = [
        'guide_id',
        'email',
        'name',
        'user_id',
        'assigned_by',
        'notify',
    ];

    protected $casts = [
        'palette' => Palette::class,
        'accent' => Accent::class,
        'appearance_at' => 'datetime',
        'notify' => 'boolean',
        'notified_at' => 'datetime',
    ];

    /**
     * A new reader starts in the guide's default colours — copied once, so a later change to the
     * default never repaints them — and with no badge: nobody has chosen these for them yet.
     */
    protected static function booted(): void
    {
        static::creating(function (Reader $reader) {
            $guide = $reader->guide ?? Guide::find($reader->guide_id);

            $reader->palette ??= $guide?->default_palette;
            $reader->accent ??= $guide?->default_accent;
        });
    }

    /** Saves this reader's colours as chosen by `admin` or `reader`. */
    public function paint(Palette $palette, Accent $accent, string $by): void
    {
        $this->forceFill([
            'palette' => $palette,
            'accent' => $accent,
            'appearance_by' => $by,
            'appearance_at' => now(),
        ])->save();
    }

    /**
     * Ticks or clears one line for this reader. Idempotent: ticking a ticked line, or clearing a
     * clear one, changes nothing — a second tap or a retried request is harmless.
     */
    public function tick(string $itemKey, bool $done): void
    {
        $row = ['user_id' => $this->user_id, 'guide_id' => $this->guide_id, 'item_key' => $itemKey];

        $done
            ? Tick::query()->insertOrIgnore([...$row, 'created_at' => now()])
            : Tick::query()->where($row)->delete();
    }

    /**
     * What the guide calls them: the name the owner gave, else the first name on their account,
     * else nothing.
     */
    public function displayName(): ?string
    {
        $name = $this->name ?: Str::of((string) $this->user?->name)->trim()->explode(' ')->first();

        return filled($name) ? $name : null;
    }

    /** @return list<string> the keys of the lines this reader has ticked on this guide */
    public function tickedKeys(): array
    {
        return Tick::query()
            ->where('user_id', $this->user_id)
            ->where('guide_id', $this->guide_id)
            ->pluck('item_key')
            ->all();
    }

    /** @return array<string, string> */
    public function appearanceTokens(): array
    {
        return Appearance::tokens($this->palette, $this->accent);
    }

    /** Stored lower-cased and trimmed: Google answers with the address as typed at sign-up. */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
