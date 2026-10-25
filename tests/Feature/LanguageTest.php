<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arcadia's language: the URL decides (`/es` · `/en`, each page under its own language's slug),
 * and where no URL does — `/`, the sign-in — it is the language this browser last used on
 * Arcadia, else Spanish. Never the browser's.
 */
class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
    }

    private function publishedGuide(string $locale): Guide
    {
        $guide = Guide::create(['locale' => $locale, 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $guide->latestVersion()->release();

        return $guide->fresh();
    }

    public function test_the_root_is_spanish_whatever_the_browser_speaks(): void
    {
        foreach (['es-VE', 'en-US', 'fr-FR'] as $language) {
            $this->flushSession();

            $this->withHeader('Accept-Language', $language)
                ->get('/')
                ->assertRedirect('/es');
        }
    }

    public function test_the_root_remembers_the_language_last_used_here(): void
    {
        $this->get('/en')->assertOk();
        $this->get('/')->assertRedirect('/en');

        $this->get('/privacidad')->assertOk();
        $this->get('/')->assertRedirect('/es');
    }

    public function test_a_signed_in_owner_is_remembered_in_the_editors_language(): void
    {
        $this->actingAs($this->owner())->get('/en/editor')->assertOk();

        $this->get('/settings/profile')->assertInertia(fn ($page) => $page->where('locale', 'en'));
        $this->get('/')->assertRedirect('/en');
    }

    public function test_a_guide_opens_only_under_its_own_languages_slug(): void
    {
        $spanish = $this->publishedGuide('es');
        $english = $this->publishedGuide('en');

        // The owner reads any guide; a reader needs an assignment (`ReaderDashboardTest`).
        $this->actingAs($this->owner());

        $this->get('/guia/'.$spanish->link_token)->assertOk()->assertInertia(fn ($page) => $page->where('locale', 'es'));
        $this->get('/guide/'.$spanish->link_token)->assertNotFound();

        $this->get('/guide/'.$english->link_token)->assertOk()->assertInertia(fn ($page) => $page->where('locale', 'en'));
        $this->get('/guia/'.$english->link_token)->assertNotFound();
    }

    public function test_the_editor_speaks_the_language_of_its_url(): void
    {
        Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        Guide::create(['locale' => 'en', 'title' => 'Excel for work', 'level' => Level::BASIC]);

        $this->actingAs($this->owner())
            ->get('/es/editor/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'es')
                ->where('guides.total', 1)
                ->where('guides.data.0.title', 'Excel para el trabajo'));

        $this->get('/en/editor/guides')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'en')
                ->where('guides.data.0.title', 'Excel for work'));
    }

    public function test_the_sign_in_speaks_the_language_last_used_here_not_the_browsers(): void
    {
        $this->withHeader('Accept-Language', 'en-US')
            ->get('/login')
            ->assertInertia(fn ($page) => $page->component('auth/Login')->where('locale', 'es'));

        $this->get('/en');
        $this->get('/login')->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }

    public function test_a_sign_in_lands_in_the_editor_of_the_language_last_used_here(): void
    {
        $owner = $this->owner();

        $this->get('/en');

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect('/en/editor');
    }
}
