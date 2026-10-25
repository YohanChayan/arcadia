<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What the owner sets for one language: the price a guide is offered at, in US dollars, and an
 * optional short note shown beside it (`BCV`: the dollar at Venezuela's central-bank rate). Shown
 * on the landing page and in its popup; nothing is charged through the site — payment is later
 * work.
 *
 * And how long one step of a guide should take to watch, in minutes: the range the research prompt
 * asks for. It is the default only — a guide's prompt is filled starting from it
 * (`PromptVariableType::STEP_LENGTH`) and keeps whatever it was filled with.
 *
 * A host whose row is missing reads the default rather than failing: `GuideSettingsSeeder`
 * writes the rows, and the default is stated once, here.
 */
class Setting extends Model
{
    public const DEFAULT_PRICE = '3.00';

    public const DEFAULT_STEP_MIN_MINUTES = 4;

    public const DEFAULT_STEP_MAX_MINUTES = 8;

    protected $table = 'guide_settings';

    protected $fillable = ['locale', 'price', 'price_note', 'step_min_minutes', 'step_max_minutes'];

    protected $casts = [
        'price' => 'decimal:2',
        'step_min_minutes' => 'integer',
        'step_max_minutes' => 'integer',
    ];

    public static function forLocale(string $locale): self
    {
        return static::query()->firstOrNew(['locale' => $locale], [
            'price' => self::DEFAULT_PRICE,
            'step_min_minutes' => self::DEFAULT_STEP_MIN_MINUTES,
            'step_max_minutes' => self::DEFAULT_STEP_MAX_MINUTES,
        ]);
    }

    /** `4 a 8`, `4 to 8` — the step length as a prompt reads it, before "minutes". */
    public function stepLengthLabel(): string
    {
        return __('arcadia.step_length', ['min' => $this->step_min_minutes, 'max' => $this->step_max_minutes]);
    }

    /** A price of 0 is no price: the landing page then leaves it out. */
    public function hasPrice(): bool
    {
        return (float) $this->price > 0;
    }

    /** `$3`, or `$2.50` — no cents when there are none. */
    public function priceLabel(): string
    {
        $price = (float) $this->price;

        return '$'.(fmod($price, 1.0) === 0.0 ? number_format($price, 0) : number_format($price, 2));
    }
}
