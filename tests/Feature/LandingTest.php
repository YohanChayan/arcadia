<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arcadia's home is its landing page, in its own language: a path it never had is a 404,
 * rendered in Arcadia's own document. The coming-soon link it replaced still leads home.
 */
class LandingTest extends TestCase
{
    // The price it shows is read from the database.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Absolute guide URLs take their scheme and port from `APP_URL`; pin production's.
        config(['app.url' => 'https://arcadia.yhndevside.com']);
    }

    public function test_the_landing_page_renders_in_the_language_of_its_url(): void
    {
        // An English browser at `/es` still gets Spanish: the URL decides.
        $this->withHeader('Accept-Language', 'en-US')
            ->get('/es')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing')
                ->where('locale', 'es')
                ->where('noIndex', true)
                ->where('canonicalUrl', 'https://arcadia.yhndevside.com/es')
                ->where('alternates', ['en' => 'https://arcadia.yhndevside.com/en']));

        $this->get('/en')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'en')
                ->where('canonicalUrl', 'https://arcadia.yhndevside.com/en')
                ->where('alternates', ['es' => 'https://arcadia.yhndevside.com/es']));
    }

    public function test_arcadia_names_itself_in_both_languages(): void
    {
        foreach (['es' => 'Entrar · Arcadia', 'en' => 'Sign in · Arcadia'] as $locale => $signIn) {
            $this->get('/'.$locale)->assertInertia(fn ($page) => $page
                ->where('pageTitle', fn ($title) => str_starts_with($title, 'Arcadia · '))
                ->where('metaDescription', fn ($text) => str_contains($text, 'Arcadia'))
                ->where('ogImageAlt', fn ($text) => str_starts_with($text, 'Arcadia — '))
                ->where('ogImage', "/img/arcadia/og-arcadia-{$locale}.jpg")
                ->where('siteName', 'Arcadia')
                ->where('noIndex', true));

            // A shared guide link previews as the sign-in, to a signed-out crawler: Arcadia's preview.
            $this->get('/login')->assertInertia(fn ($page) => $page
                ->where('pageTitle', $signIn)
                ->where('ogImage', "/img/arcadia/og-arcadia-{$locale}.jpg")
                ->where('siteName', 'Arcadia'));
        }

        $manifest = json_decode(file_get_contents(public_path('img/arcadia/site.webmanifest')), true);
        $this->assertSame(['Arcadia', 'Arcadia'], [$manifest['name'], $manifest['short_name']]);

        // No former host or old name left in what a visitor reads.
        foreach (['es', 'en'] as $locale) {
            $copy = json_encode(trans('arcadia', [], $locale), JSON_UNESCAPED_UNICODE);
            foreach (['aprende.', 'learning.yhndevside', 'YHN Dev Side · Learning'] as $old) {
                $this->assertStringNotContainsString($old, $copy, "{$locale}: {$old}");
            }
        }
    }

    public function test_every_public_page_stays_out_of_search_engines(): void
    {
        foreach (['/es', '/en', '/privacidad', '/privacy', '/login'] as $path) {
            $this->get($path)->assertInertia(fn ($page) => $page->where('noIndex', true));
        }
    }

    public function test_the_page_knows_where_its_popup_asks_for_a_guide(): void
    {
        $this->get('/es')->assertInertia(fn ($page) => $page
            ->where('inquiryUrl', '/pedir')
            ->where('privacyUrl', '/privacidad')
            ->where('loginUrl', '/login'));

        $this->get('/en')->assertInertia(fn ($page) => $page->where('inquiryUrl', '/ask'));
    }

    public function test_the_popup_knows_the_verified_account_a_request_is_recorded_with(): void
    {
        $this->get('/es')->assertInertia(fn ($page) => $page->where('requester', null));

        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)
            ->create(['email' => 'ana@gmail.com', 'email_verified_at' => null]));
        $this->get('/es')->assertInertia(fn ($page) => $page->where('requester', null));

        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']));
        $this->get('/es')->assertInertia(fn ($page) => $page->where('requester.email', 'bob@gmail.com'));
    }

    public function test_each_language_has_its_privacy_note(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Privacy')
                ->where('noIndex', true)
                ->where('email', 'yhndevside@gmail.com')
                ->where('locale', 'es'));

        $this->get('/privacy')->assertOk()->assertInertia(fn ($page) => $page->where('locale', 'en'));

        // Each path is its own language's, whatever was read before.
        $this->get('/privacy')->assertInertia(fn ($page) => $page->where('locale', 'en'));
        $this->get('/privacidad')->assertInertia(fn ($page) => $page->where('locale', 'es'));
    }

    public function test_the_page_uses_the_arcadia_root_view_without_analytics(): void
    {
        $this->get('/es')
            ->assertOk()
            ->assertSee('data-site="arcadia"', false)
            ->assertDontSee('googletagmanager', false)
            ->assertDontSee('google-site-verification', false);
    }

    public function test_a_path_the_host_never_had_is_a_404_in_its_own_document(): void
    {
        // The studio's paths, and any unknown path, match nothing here: both are 404.
        foreach (['/about-us', '/servicios', '/user/two-factor-qr-code', '/dashboard', '/admin', '/borrador', '/no/such/page'] as $path) {
            $this->get($path)
                ->assertNotFound()
                ->assertSee('data-site="arcadia"', false)
                ->assertDontSee('googletagmanager', false);
        }

        // Two-factor is not offered.
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertNotFound();
    }
}
