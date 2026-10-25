<?php

namespace App\Enums;

/**
 * The page colours a guide may be read in — the ground, surfaces, text and rules. Curated rather
 * than free, so a reader cannot make their guide unreadable: every palette, with every `Accent`,
 * is held to the contrast floors by `GuideAppearanceTest`. Dark only, as Arcadia is (ADR 0016).
 *
 * `ARCADIA` sets nothing: the page keeps the site's own look, its glow and translucent surfaces
 * included. Its colours below are what it is checked against, not what it renders.
 */
enum Palette: string
{
    case ARCADIA = 'arcadia';
    case GRAPHITE = 'graphite';
    case FOREST = 'forest';
    case PLUM = 'plum';
    case COCOA = 'cocoa';

    public function label(): string
    {
        return __("arcadia.appearance.palettes.{$this->value}");
    }

    /**
     * Its colours, by reader token.
     *
     * @return array{ground: string, surface: string, well: string, ink: string, ink_soft: string, rule: string}
     */
    public function colours(): array
    {
        return match ($this) {
            self::ARCADIA => ['ground' => '#061A33', 'surface' => '#0B2240', 'well' => '#051428', 'ink' => '#EBEEF7', 'ink_soft' => '#7B8697', 'rule' => '#1D2F47'],
            self::GRAPHITE => ['ground' => '#111214', 'surface' => '#1C1D21', 'well' => '#16171A', 'ink' => '#ECECEE', 'ink_soft' => '#A7A8AE', 'rule' => '#2C2D33'],
            self::FOREST => ['ground' => '#07130E', 'surface' => '#0F2219', 'well' => '#0B1A13', 'ink' => '#E6F2EC', 'ink_soft' => '#9DB8AB', 'rule' => '#1D3529'],
            self::PLUM => ['ground' => '#120A17', 'surface' => '#1F1328', 'well' => '#180F20', 'ink' => '#F1E9F5', 'ink_soft' => '#B6A6C0', 'rule' => '#33223F'],
            self::COCOA => ['ground' => '#14100C', 'surface' => '#221B15', 'well' => '#1A1511', 'ink' => '#F3ECE4', 'ink_soft' => '#BBAE9F', 'rule' => '#3A2F25'],
        };
    }

    /**
     * The reader tokens it sets on the page's root — none for `ARCADIA`.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        if ($this === self::ARCADIA) {
            return [];
        }

        $tokens = [];

        foreach ($this->colours() as $name => $hex) {
            $tokens['--guide-'.str_replace('_', '-', $name)] = $hex;
        }

        return $tokens;
    }
}
