<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

/**
 * Passkeys are optional twice over: `PASSKEYS_ENABLED` switches the whole feature, and each
 * account chooses to add one in Settings. A passkey signs in without Fortify's
 * `authenticateUsing`, so admission is asked again before it may (`FortifyServiceProvider`).
 */
class PasskeysTest extends TestCase
{
    use RefreshDatabase;

    private function passkeyOf(User $user): Passkey
    {
        return (new Passkey)->setRelation('user', $user);
    }

    private function signInRequest(): Request
    {
        return Request::create('/passkeys/login', 'POST');
    }

    public function test_the_sign_in_and_the_confirmation_offer_a_passkey_while_the_feature_is_on(): void
    {
        $this->assertTrue(Features::enabled(Features::passkeys()));

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canUsePasskeys', true));
        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create())
            ->get('/user/confirm-password')
            ->assertInertia(fn ($page) => $page->component('auth/ConfirmPassword')->where('canUsePasskeys', true));
    }

    public function test_switched_off_no_page_offers_a_passkey(): void
    {
        config(['fortify.features' => array_values(array_filter(
            config('fortify.features'),
            fn ($feature) => $feature !== Features::passkeys(),
        ))]);

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canUsePasskeys', false));

        $reader = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->actingAs($reader)
            ->get('/user/confirm-password')
            ->assertInertia(fn ($page) => $page->where('canUsePasskeys', false));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/security')
            ->assertInertia(fn ($page) => $page->where('canManagePasskeys', false));
    }

    public function test_a_passkey_signs_in_only_whom_arcadia_admits(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create();
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $unapprovedOwner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => null]);
        $nobody = User::factory()->create();

        $this->assertTrue(Passkeys::allowsLogin($this->signInRequest(), $this->passkeyOf($reader)));
        $this->assertTrue(Passkeys::allowsLogin($this->signInRequest(), $this->passkeyOf($owner)));

        // A membership removed after the passkey was added: the passkey goes with it.
        $this->assertFalse(Passkeys::allowsLogin($this->signInRequest(), $this->passkeyOf($nobody)));
        $this->assertFalse(Passkeys::allowsLogin($this->signInRequest(), $this->passkeyOf($unapprovedOwner)));
    }
}
