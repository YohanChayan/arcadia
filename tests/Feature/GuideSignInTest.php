<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arcadia's sign-in is the owner's door, guarded twice: the site admits only the owner at
 * authentication (`ArcadiaSite::admits()`), and the editor's routes refuse anyone else at the
 * route (`role:admin`). Each gate is proved on its own here.
 */
class GuideSignInTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(?int $aliasNumber): User
    {
        // The role is the membership's; null is an account with none.
        return User::factory()
            ->when($aliasNumber !== null, fn ($factory) => $factory->withRole($aliasNumber))
            ->create([
                'approved_at' => now(),
                'email_verified_at' => now(),
            ]);
    }

    public function test_the_guide_host_renders_its_own_sign_in_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->where('noIndex', true)
                ->where('locale', 'es'));
    }

    public function test_the_owner_signs_in_and_lands_in_the_editor(): void
    {
        $admin = $this->userWith(Role::ALIAS_ADMIN);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/es/editor');

        $this->assertAuthenticatedAs($admin);

        $this->get('/es/editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('editor/Home'));
    }

    public function test_an_account_without_a_membership_is_refused_at_sign_in_as_a_wrong_password_would_be(): void
    {
        $user = $this->userWith(null);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unapproved_admin_is_refused_too(): void
    {
        $admin = $this->userWith(Role::ALIAS_ADMIN);
        $admin->forceFill(['approved_at' => null])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_editor_refuses_at_the_route_whoever_got_past_sign_in(): void
    {
        // A guest is sent to the sign-in.
        $this->get('/es/editor')->assertRedirect('/login');

        // A session without an Arcadia role meets the role gate.
        $this->actingAs($this->userWith(null))
            ->get('/es/editor')
            ->assertForbidden();
    }

    public function test_signing_out_works_on_the_guide_host(): void
    {
        $this->actingAs($this->userWith(Role::ALIAS_ADMIN))
            ->post('/logout')
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_each_host_keeps_its_own_session(): void
    {
        // A session cookie with a `Domain` would reach every subdomain of it, the studio's
        // included. Host-only, with no domain, keeps it Arcadia's.
        $cookie = collect($this->get('/login')->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'No session cookie.');
        $this->assertEmpty($cookie->getDomain(), 'The session cookie is shared across hosts.');
    }

    public function test_a_signed_in_owner_at_the_sign_in_goes_on_to_the_editor(): void
    {
        $this->actingAs($this->userWith(Role::ALIAS_ADMIN))
            ->get('/login')
            ->assertRedirect('/es/editor');
    }

    public function test_two_factor_is_not_offered(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();
    }
}
