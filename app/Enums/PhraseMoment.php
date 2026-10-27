<?php

namespace App\Enums;

/**
 * When a motivation phrase is shown: on arriving at "My guides", or on a guide's card for where
 * the reader stands on it — not started, under way, nearly done (three quarters), done.
 */
enum PhraseMoment: string
{
    case WELCOME = 'welcome';
    case START = 'start';
    case PROGRESS = 'progress';
    /** A stage just completed, on the guide page — never the whole guide, which is `DONE`. */
    case STAGE_DONE = 'stage_done';
    case ALMOST = 'almost';
    case DONE = 'done';

    /** The moment of a guide, from the lines ticked of its total. */
    public static function forProgress(int $done, int $total): self
    {
        return match (true) {
            $done === 0 => self::START,
            $total > 0 && $done >= $total => self::DONE,
            $total > 0 && $done / $total >= 0.75 => self::ALMOST,
            default => self::PROGRESS,
        };
    }

    public function label(): string
    {
        return __("arcadia.editor.phrases.moments.{$this->value}");
    }

    /** @return list<array{value: string, label: string}> every moment, in a reader's order, for a form */
    public static function options(): array
    {
        return array_map(fn (self $moment) => ['value' => $moment->value, 'label' => $moment->label()], self::cases());
    }

    /**
     * The moments a guide chooses its own phrases for: a guide's card and page. Not the welcome,
     * which greets a reader on "My guides" — a page of every guide of theirs, so no one guide's.
     *
     * @return list<self>
     */
    public static function perGuide(): array
    {
        return array_values(array_filter(self::cases(), fn (self $moment) => $moment !== self::WELCOME));
    }

    /** @return list<array{value: string, label: string}> the per-guide moments, for the guide form */
    public static function guideOptions(): array
    {
        return array_map(fn (self $moment) => ['value' => $moment->value, 'label' => $moment->label()], self::perGuide());
    }
}
