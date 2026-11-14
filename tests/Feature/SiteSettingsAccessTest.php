<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Site settings is the owner's alone: every route of it, in both editors, reads and actions
 * alike, turns a reader away and sends a guest to sign in. The section adds no gate of its own —
 * it inherits the editor's `role:admin` — so a route registered outside that group would be open,
 * and this is where it would show. Each new tab's routes join the list.
 */
class SiteSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return collect(['es', 'en'])->flatMap(fn (string $locale) => [
            "{$locale} host settings" => ['get', "/{$locale}/editor/settings"],
            "{$locale} save host settings" => ['put', "/{$locale}/editor/settings"],
            "{$locale} health" => ['get', "/{$locale}/editor/settings/health"],
            "{$locale} retry a failed job" => ['post', "/{$locale}/editor/settings/health/failed-jobs/".self::UUID.'/retry'],
            "{$locale} delete a failed job" => ['delete', "/{$locale}/editor/settings/health/failed-jobs/".self::UUID],
            "{$locale} flush failed jobs" => ['delete', "/{$locale}/editor/settings/health/failed-jobs"],
            "{$locale} send a test email" => ['post', "/{$locale}/editor/settings/health/test-mail"],
            "{$locale} logs" => ['get', "/{$locale}/editor/settings/logs"],
            "{$locale} agents" => ['get', "/{$locale}/editor/settings/agents"],
            "{$locale} mail" => ['get', "/{$locale}/editor/settings/mail"],
        ])->all();
    }

    #[DataProvider('routes')]
    public function test_a_guest_is_sent_to_sign_in(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect();
    }

    #[DataProvider('routes')]
    public function test_a_reader_is_turned_away(string $method, string $uri): void
    {
        // Approved and verified, so it is `role:admin` that refuses them, not the editor's floor.
        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->{$method}($uri)->assertForbidden();
    }

    public function test_the_owner_opens_site_settings_on_its_host_tab(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->get('/es/editor/settings')->assertOk()->assertInertia(fn ($page) => $page->component('editor/settings/Edit'));
    }
}
