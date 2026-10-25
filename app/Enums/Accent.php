<?php

namespace App\Enums;

/**
 * The one colour a guide's reader picks beside its `Palette`: progress, the stage numbers, the
 * category line, a link. Each is light enough to sit on every palette, with dark text on it —
 * `GuideAppearanceTest` holds every pairing to that. `TEAL` is Arcadia's own (`OWN`): it sets no
 * tokens, so the page keeps the site's accent — teal since 2026-10-15, royal blue before (ADR 0019).
 */
enum Accent: string
{
    case TEAL = 'teal';
    case BLUE = 'blue';
    case SKY = 'sky';
    case VIOLET = 'violet';
    case PINK = 'pink';
    case CORAL = 'coral';
    case AMBER = 'amber';

    /** Arcadia's own — the default for a guide and a reader who picked nothing. */
    public const OWN = self::TEAL;

    /** The text set on the accent — one dark ink for all of them. */
    public const ON_ACCENT = '#0B0D12';

    public function label(): string
    {
        return __("arcadia.appearance.accents.{$this->value}");
    }

    public function hex(): string
    {
        return match ($this) {
            self::TEAL => '#2EE6B8',
            self::BLUE => '#5B8CFF',
            self::SKY => '#38BDF8',
            self::VIOLET => '#A78BFA',
            self::PINK => '#F472B6',
            self::CORAL => '#FB7185',
            self::AMBER => '#FBBF24',
        };
    }

    /** @return array<string, string> */
    public function tokens(): array
    {
        return ['--guide-accent' => $this->hex(), '--guide-on-accent' => self::ON_ACCENT];
    }
}
