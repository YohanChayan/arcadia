<?php

namespace App\Models;

use App\Enums\Accent;
use App\Enums\GuideStatus;
use App\Enums\ImageSlot;
use App\Enums\Level;
use App\Enums\Palette;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One guide, in one language. Reached by its link token — minted here, never taken from input,
 * and rotated to revoke a shared link — and, while it is open for enrollment, listed in the
 * catalogue (`scopeListed`, ADR 0023).
 *
 * Its content lives in versions, each a full copy. Version 1 is created with the guide.
 */
class Guide extends Model
{
    protected $fillable = [
        'locale',
        'title',
        'level',
        'category_id',
        'counterpart_id',
        'status',
        'open_for_enrollment',
        'unlock_in_order',
        'greeting',
        'pitch',
        'goals',
        'default_palette',
        'default_accent',
    ];

    protected $casts = [
        'level' => Level::class,
        'status' => GuideStatus::class,
        'default_palette' => Palette::class,
        'default_accent' => Accent::class,
        'open_for_enrollment' => 'boolean',
        'unlock_in_order' => 'boolean',
        'prompt_values' => 'array',
        'curator_notes' => 'array',
        // Its own lines of text — what the reader will be able to do — never a shared term (ADR 0020).
        'goals' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Guide $guide) {
            $guide->link_token ??= self::newLinkToken();
        });

        static::created(function (Guide $guide) {
            $guide->versions()->create(['number' => 1]);
        });

        // One by one, so each takes its files with it; the rows alone would cascade.
        static::deleting(function (Guide $guide) {
            $guide->images->each->delete();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'link_token';
    }

    /**
     * A new link, and the old one stops working at once. For a link that was shared too widely.
     */
    public function rotateLinkToken(): void
    {
        $this->forceFill(['link_token' => self::newLinkToken()])->save();
    }

    /**
     * What a reader is given: the latest released version, or null while nothing is released.
     * Pinning a buyer to the version they bought is the payment pack's.
     */
    public function releasedVersion(): ?Version
    {
        return $this->versions()->reorder('number', 'desc')->whereNotNull('released_at')->first();
    }

    /** A pitch is a few lines, not a sales letter. */
    public const MAX_PITCH = 600;

    /** At most this many goals: a guide with more is promising too much. */
    public const MAX_GOALS = 8;

    /**
     * Goals as stored: each line trimmed, blanks and repeats dropped, in the order given — from the
     * form, an AI's answer or a request alike.
     *
     * @param  iterable<mixed>|null  $lines
     * @return list<string>
     */
    public static function goalLines(?iterable $lines): array
    {
        return collect($lines ?? [])
            ->map(fn ($line) => Str::limit(trim((string) $line), 160, ''))
            ->filter()
            ->unique(fn (string $line) => mb_strtolower($line))
            ->take(self::MAX_GOALS)
            ->values()
            ->all();
    }

    /** Whether a reader can open it: published, with a released version. */
    public function isReadable(): bool
    {
        return $this->status === GuideStatus::PUBLISHED && $this->releasedVersion() !== null;
    }

    /**
     * The guides the catalogue lists (ADR 0023): those anyone may ask for — open for enrollment —
     * and could read once let in: published, with a released version. A guide made for one person
     * is never open, so it is never listed.
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', GuideStatus::PUBLISHED)
            ->where('open_for_enrollment', true)
            ->whereHas('versions', fn (Builder $versions) => $versions->whereNotNull('released_at'));
    }

    /** The newest version, released or still a draft — what the editor works on. */
    public function latestVersion(): Version
    {
        return $this->versions()->reorder('number', 'desc')->firstOrFail();
    }

    /** Who this guide is for, by email — many per guide. */
    public function readers(): HasMany
    {
        return $this->hasMany(Reader::class);
    }

    /** Signed-in accounts asking to enroll in it — pending, approved or declined. */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class);
    }

    /** Its images, one per `ImageSlot`. */
    public function images(): HasMany
    {
        return $this->hasMany(GuideImage::class);
    }

    /** Its cover, or null while none is uploaded. Loads its images once if they are not yet. */
    public function cover(): ?GuideImage
    {
        return $this->loadMissing('images')->images->firstWhere('slot', ImageSlot::COVER);
    }

    /** The request this guide answers, when it was started from one. */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** The prompt version that researched it, filled with `prompt_values`. */
    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'prompt_version_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(self::class, 'counterpart_id');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'guide_topic', 'guide_id', 'topic_id');
    }

    /** The motivation phrases chosen for it; none chosen, it draws on all of its language's. */
    public function phrases(): BelongsToMany
    {
        return $this->belongsToMany(Phrase::class, 'guide_phrase_guide', 'guide_id', 'phrase_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderBy('number');
    }

    private static function newLinkToken(): string
    {
        do {
            $token = Str::lower(Str::random(24));
        } while (self::where('link_token', $token)->exists());

        return $token;
    }
}
