<?php

namespace App\Services;

use App\Enums\Accent;
use App\Enums\Palette;

/**
 * A guide's colours as the page applies them: the reader tokens a palette and an accent set on
 * the page's root, and the choices the pickers offer. Null is Arcadia's own.
 */
class Appearance
{
    /**
     * The `--guide-*` tokens for this pairing — empty for Arcadia's own, which the stylesheet
     * already sets.
     *
     * @return array<string, string>
     */
    public static function tokens(?Palette $palette, ?Accent $accent): array
    {
        $accent ??= Accent::OWN;

        return [
            ...($palette ?? Palette::ARCADIA)->tokens(),
            ...($accent === Accent::OWN ? [] : $accent->tokens()),
        ];
    }

    /**
     * What the pickers offer: each palette with the colours of its swatch, each accent with its
     * hex. Arcadia's own first, as the default.
     *
     * @return array{palettes: list<array<string, mixed>>, accents: list<array<string, string>>, onAccent: string}
     */
    public static function options(): array
    {
        return [
            'palettes' => array_map(fn (Palette $palette) => [
                'value' => $palette->value,
                'label' => $palette->label(),
                'colours' => $palette->colours(),
            ], Palette::cases()),
            'accents' => array_map(fn (Accent $accent) => [
                'value' => $accent->value,
                'label' => $accent->label(),
                'hex' => $accent->hex(),
            ], Accent::cases()),
            'onAccent' => Accent::ON_ACCENT,
        ];
    }
}
