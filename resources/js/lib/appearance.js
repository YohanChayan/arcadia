/**
 * The `--guide-*` tokens of a palette and an accent, from the options the server offers
 * (`Appearance::options()`) — for repainting a page while colours are being picked, before they
 * are saved. Mirrors `Appearance::tokens()`: Arcadia's own palette and accent (`arcadia`, `teal`) set nothing.
 */
export const appearanceTokens = (options, palette, accent) => {
    const tokens = {}

    if (palette !== 'arcadia') {
        const colours = options.palettes.find(option => option.value === palette)?.colours ?? {}

        for (const [name, hex] of Object.entries(colours)) {
            tokens[`--guide-${name.replace('_', '-')}`] = hex
        }
    }

    if (accent !== 'teal') {
        const hex = options.accents.find(option => option.value === accent)?.hex

        if (hex) {
            tokens['--guide-accent'] = hex
            tokens['--guide-on-accent'] = options.onAccent
        }
    }

    return tokens
}
