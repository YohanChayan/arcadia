<?php

namespace App\Services;

use App\Mail\MailBrand;
use App\Models\Role;
use App\Models\User;

/**
 * Arcadia, the learning guides, on one host. **The URL decides the language**: each public page
 * and the editor are registered once per language, inside that language's route group
 * (`locale:es` · `locale:en`), under `/es` · `/en` or the language's own slug, and named
 * `<locale>.*`.
 *
 * Before a route group has spoken — `/`, Fortify's sign-in, a 404 — the language is the one this
 * browser last used here, else Spanish (`localeFor()`). Never the account's or the browser's.
 */
class ArcadiaSite
{
    /**
     * @param  array{default_locale: string, locales: array<string, array<string, string>>, contact_email: string, device_limit: int}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * The languages Arcadia is served in, in the order their routes are registered.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys($this->config['locales']);
    }

    public function defaultLocale(): string
    {
        return $this->config['default_locale'];
    }

    /** The route middleware that sets a language for the routes registered under it. */
    public function localeMiddleware(string $locale): string
    {
        return "locale:{$locale}";
    }

    /** This request's language, as its route group set it; the default before one has. */
    public function locale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, $this->locales(), true) ? $locale : $this->defaultLocale();
    }

    /**
     * The language this browser last used on Arcadia — the route groups remember it in this
     * host's session — else Spanish. Read by `ResolveLocale` for the requests no route group
     * sets: `/`, the sign-in, a 404.
     */
    public function localeFor(?string $remembered): string
    {
        return in_array($remembered, $this->locales(), true) ? $remembered : $this->defaultLocale();
    }

    /** The landing page: `/es` · `/en`. */
    public function homePath(?string $locale = null): string
    {
        return '/'.($locale ?? $this->locale());
    }

    /** The path a guide's link lives under: `guia` in Spanish, `guide` in English. */
    public function guidePath(?string $locale = null): string
    {
        return $this->slug('guide_path', $locale);
    }

    /** The link to share for a guide in that language — a guide opens only under its own. */
    public function guideUrl(string $linkToken, ?string $locale = null): string
    {
        return $this->url($this->guidePath($locale).'/'.$linkToken);
    }

    /** The guides open for enrollment, listed: `/es/guias` · `/en/guides`. */
    public function cataloguePath(?string $locale = null): string
    {
        return $this->homePath($locale).'/'.$this->slug('catalogue_path', $locale);
    }

    /** The privacy note: `/privacidad` · `/privacy`. */
    public function privacyPath(?string $locale = null): string
    {
        return '/'.$this->slug('privacy_path', $locale);
    }

    /** Where the landing page's popup sends a request: `/pedir` · `/ask`. */
    public function inquiryPath(?string $locale = null): string
    {
        return '/'.$this->slug('inquiry_path', $locale);
    }

    /** The owner's editor for one language: `/es/editor` · `/en/editor`. */
    public function editorPath(?string $locale = null): string
    {
        return $this->homePath($locale).'/editor';
    }

    /** A reader's own guides: `/es/mis-guias` · `/en/my-guides`. */
    public function readerPath(?string $locale = null): string
    {
        return $this->homePath($locale).'/'.$this->slug('reader_path', $locale);
    }

    /** What follows a guide's link to ask for it: `acceso` · `access`. */
    public function accessPath(?string $locale = null): string
    {
        return $this->slug('access_path', $locale);
    }

    /** Where an account at its device limit chooses which to keep: `/es/dispositivos` · `/en/devices`. */
    public function devicesPath(?string $locale = null): string
    {
        return $this->homePath($locale).'/'.$this->slug('devices_path', $locale);
    }

    /** A reader's help — how Arcadia works, and who to write to: `/es/ayuda` · `/en/help`. */
    public function helpPath(?string $locale = null): string
    {
        return $this->homePath($locale).'/'.$this->slug('help_path', $locale);
    }

    /** How many devices an account may read on when its membership sets no limit of its own. */
    public function deviceLimit(): int
    {
        return $this->config['device_limit'];
    }

    /** The contact address readers are given, in the privacy note and the request popup. */
    public function contactEmail(): ?string
    {
        return $this->config['contact_email'] ?: null;
    }

    /** This user's role on Arcadia — their membership's — or null when they have none. */
    public function roleOf(User $user): ?Role
    {
        return $user->membership()->first()?->role;
    }

    /**
     * Whoever holds an Arcadia membership: the owner, approved, so the floor the editor's routes
     * sit behind can never bounce a user this admitted; and a reader, who is never approved —
     * the membership is their admission. Approval is the owner's alone.
     */
    public function admits(User $user): bool
    {
        return match ($this->roleOf($user)?->alias) {
            'admin' => $user->approved_at !== null,
            'reader' => true,
            default => false,
        };
    }

    public function loginPage(): string
    {
        return 'auth/Login';
    }

    /** Teal on navy, as the site is (ADR 0019); dark text on the teal. */
    public function mailBrand(): MailBrand
    {
        return new MailBrand(
            name: 'Arcadia',
            homeUrl: $this->url($this->homePath()),
            ground: '#030D1C',
            panel: '#0B2240',
            ink: '#EBEEF7',
            inkSoft: '#EBEEF7B3',
            accent: '#2EE6B8',
            onAccent: '#061A33',
            eyebrow: '#2DD6F5',
            rule: '#EBEEF71A',
        );
    }

    /**
     * The page and props of one of Fortify's other screens — `register`, `forgot-password`,
     * `reset-password`, `verify-email`. Its sign-in page stays `loginPage()`.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function authPage(string $screen): array
    {
        $page = ['register' => 'auth/Register', 'forgot-password' => 'auth/ForgotPassword',
            'reset-password' => 'auth/ResetPassword', 'verify-email' => 'auth/VerifyEmail'][$screen];

        return [$page, [
            'pageTitle' => __("arcadia.auth.screens.{$screen}.title").' · Arcadia',
            'noIndex' => true,
        ]];
    }

    /**
     * An account registered here is a reader of Arcadia: anyone may hold one (2026-10-15), and it
     * reads no guide until one is assigned to its email — and it is verified. Never approved:
     * approval is the owner's.
     */
    public function registered(User $user): void
    {
        $user->membership()->firstOrCreate(
            [],
            ['role_id' => Role::where('alias_number', Role::ALIAS_READER)->orderBy('id')->firstOrFail()->id],
        );
    }

    /**
     * The sign-in page's own props — its SEO meta. The status message and the feature switches
     * are added by Fortify's view, which has the request.
     *
     * @return array<string, mixed>
     */
    public function loginProps(): array
    {
        return [
            'pageTitle' => __('arcadia.auth.meta.title'),
            // Unlinked, and for one person: nothing here for a search engine.
            'noIndex' => true,
            'canonicalUrl' => $this->url('login'),
            // A shared guide link sends a signed-out preview crawler here, so this is its preview.
            ...$this->preview(),
        ];
    }

    /**
     * What a link to Arcadia previews as: its social image in this language, and Arcadia's name.
     * A new artwork ships under a new file name: platforms cache a preview by URL — hence
     * `og-arcadia-*`, the Arcadia cards of 2026-10-21, where Arcadia's were `og-*`.
     *
     * @return array{ogImage: string, ogImageAlt: string, siteName: string, siteDomain: string}
     */
    public function preview(): array
    {
        return [
            'ogImage' => '/img/arcadia/og-arcadia-'.$this->locale().'.jpg',
            'ogImageAlt' => __('arcadia.landing.meta.image_alt'),
            'siteName' => 'Arcadia',
            'siteDomain' => parse_url(config('app.url'), PHP_URL_HOST) ?: '',
        ];
    }

    /**
     * In the language this browser last used here: a reader's own guides, else the editor —
     * which refuses whoever is not the owner.
     */
    public function homeAfterSignIn(?User $user = null): string
    {
        $locale = $this->localeFor(session('locale'));

        return $user && $this->roleOf($user)?->alias === 'reader'
            ? $this->readerPath($locale)
            : $this->editorPath($locale);
    }

    /**
     * An absolute URL on `APP_URL`, which emails link to: mail is sent from the queue, where there
     * is no request to take the host from.
     */
    public function url(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function slug(string $key, ?string $locale): string
    {
        return $this->config['locales'][$locale ?? $this->locale()][$key];
    }
}
