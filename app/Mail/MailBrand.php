<?php

namespace App\Mail;

/**
 * How a site looks and signs in an email: its name, its home, and its colours as literals.
 *
 * Literals, not tokens: mail clients strip stylesheets and know nothing of `app.css`, so every
 * colour is inlined from here. Each site answers with its own (`Site::mailBrand()`) — Arcadia's
 * teal on navy.
 */
final class MailBrand
{
    public function __construct(
        public readonly string $name,
        public readonly string $homeUrl,
        // The page behind the card, and the card.
        public readonly string $ground,
        public readonly string $panel,
        // Body text, and the quieter text — a hex with alpha, as mail clients take it.
        public readonly string $ink,
        public readonly string $inkSoft,
        // The button, the text on it, and the small label above the heading.
        public readonly string $accent,
        public readonly string $onAccent,
        public readonly string $eyebrow,
        public readonly string $rule,
    ) {}
}
