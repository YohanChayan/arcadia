<?php

namespace App\Models;

use App\Enums\InquiryPurpose;
use App\Enums\InquiryStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Someone asking for a guide. One contact field — "your WhatsApp or email" — because a visitor
 * asked to choose a channel first is a visitor who leaves; what it is is worked out here, on
 * every save, so the form never sends it and the two cannot disagree.
 *
 * No account is needed to ask. When the visitor was signed in with a verified email, the request
 * records the account (`user_id`): that is the one to assign the guide to.
 *
 * `prompt_version_id`, `prompt_values` and `prompted_at` hold a prompt copied for the request
 * before 2026-10-22, when that step moved onto the guide. Nothing writes them now; a guide started
 * from such a request inherits them (`StartGuideFromInquiry`).
 */
class Inquiry extends Model
{
    public const EMAIL = 'email';

    public const WHATSAPP = 'whatsapp';

    protected $table = 'guide_inquiries';

    protected $fillable = ['user_id', 'locale', 'topic', 'purpose', 'contact', 'name'];

    protected $casts = [
        'purpose' => InquiryPurpose::class,
        'prompt_values' => 'array',
        'prompted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $inquiry) => $inquiry->contact_kind = Str::contains($inquiry->contact, '@')
            ? self::EMAIL
            : self::WHATSAPP);
    }

    /** The account that asked, when they were signed in and it still exists. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who asked: the account's name, else the one typed on an older request, else nobody. */
    public function requesterName(): ?string
    {
        return $this->user?->name ?? $this->name;
    }

    /** The guide started to answer it, if there is one. */
    public function guide(): HasOne
    {
        return $this->hasOne(Guide::class);
    }

    /** Where it stands: whether a guide answers it. Reads the `guide` relation. */
    public function stage(): InquiryStage
    {
        return $this->guide !== null ? InquiryStage::GUIDE : InquiryStage::PENDING;
    }

    /** The requests that stand at `$stage`, by the same reading as `stage()`. */
    public function scopeAtStage(Builder $query, InquiryStage $stage): void
    {
        match ($stage) {
            InquiryStage::GUIDE => $query->whereHas('guide'),
            InquiryStage::PENDING => $query->whereDoesntHave('guide'),
        };
    }

    /**
     * Where answering opens: a mail, or a WhatsApp chat.
     *
     * A number typed the Venezuelan way, from its trunk `0` (`0414 123 4567`), is given the
     * country code `wa.me` needs; one typed with a `+` or a leading `00` already carries its own.
     */
    public function replyUrl(): string
    {
        if ($this->contact_kind === self::EMAIL) {
            return 'mailto:'.$this->contact;
        }

        $digits = preg_replace('/\D/', '', $this->contact);

        $international = match (true) {
            str_starts_with(trim($this->contact), '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '0') => '58'.substr($digits, 1),
            default => $digits,
        };

        return 'https://wa.me/'.$international;
    }
}
