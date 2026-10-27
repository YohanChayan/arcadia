<?php

namespace Tests\Feature;

use App\Enums\Accent;
use App\Enums\AccessRequestStatus;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Enums\Palette;
use App\Models\AccessRequest;
use App\Models\AuthProvider;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/**
 * Guides behind Google sign-in on Arcadia: who the sign-in lets in — decided before anything is
 * written — where it lands them, and what the link shows to each kind of account.
 */
class GuideSignInGateTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        AuthProvider::firstOrCreate(['alias' => 'google', 'icon' => 'google.svg']);
        Role::firstOrCreate(['alias_number' => Role::ALIAS_READER], ['name' => 'Reader']);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    /** Google answers the callback with this account. */
    private function google(string $email, bool $verified = true, string $id = '1001'): void
    {
        $user = (new GoogleUser)->setRaw(['email' => $email, 'email_verified' => $verified])->map([
            'id' => $id, 'name' => 'Ana Pérez', 'email' => $email, 'avatar' => null, 'nickname' => null,
        ]);
        $user->setToken('token');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    // ── The sign-in ──────────────────────────────────────────────────────────────────────────

    public function test_signing_in_starts_at_google_on_this_host_with_the_account_picker(): void
    {
        // The callback is an absolute URL on Arcadia's configured host; pin production's scheme.
        config(['app.url' => 'https://arcadia.yhndevside.com']);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->with('https://arcadia.yhndevside.com/auth/google/callback')->andReturnSelf();
        $provider->shouldReceive('with')->once()->with(['prompt' => 'select_account'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_an_assigned_email_signs_in_with_no_form_and_no_approval_and_lands_on_the_guide(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        // The link first: signed out, it presents the guide; signing in from it remembers where
        // they were going — the link itself.
        $this->get($this->link())->assertOk()->assertInertia(fn ($page) => $page
            ->component('GuidePresentation')
            ->where('state', 'sign_in')
            ->where('signInUrl', fn ($url) => str_ends_with($url, '/guia/'.$this->guide->link_token.'?signin=1')));
        $this->get($this->link().'?signin=1')->assertRedirect('/login');

        $this->google('Ana@Gmail.com');
        $this->get('/auth/google/callback')->assertRedirect($this->link());

        $user = User::firstWhere('email', 'Ana@Gmail.com');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->approved_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('reader', $user->membership->role->alias);
        $this->assertSame($user->id, $reader->fresh()->user_id);

        $this->get($this->link())->assertOk()->assertInertia(fn ($page) => $page->component('Guide'));
    }

    public function test_with_nothing_intended_a_reader_lands_on_their_guides(): void
    {
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback')->assertRedirect('/es/mis-guias');
    }

    public function test_a_google_sign_in_confirms_so_a_reader_with_no_password_may_add_a_passkey(): void
    {
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback');

        // A Google-made account's password is random and known to nobody; Google stands in for it.
        $this->get('/user/passkeys/options')->assertOk();
        $this->get('/settings/security')->assertOk();
    }

    public function test_a_google_sign_in_offers_a_passkey_once_to_an_account_with_none(): void
    {
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback');

        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->where('offerPasskey', true));
        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->where('offerPasskey', false));
    }

    public function test_an_account_that_already_has_a_passkey_is_not_offered_one(): void
    {
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback');
        $user = User::firstWhere('email', 'ana@gmail.com');
        $user->passkeys()->create(['name' => 'iPhone', 'credential_id' => 'abc', 'credential' => []]);
        Auth::logout();

        $this->get('/auth/google/callback');

        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->where('offerPasskey', false));
    }

    public function test_without_a_google_sign_in_adding_a_passkey_still_asks_to_confirm(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create())
            ->get('/user/passkeys/options')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_anyone_verified_gets_an_account_and_reads_nothing_not_assigned(): void
    {
        // Anyone may hold an Arcadia account since 2026-10-15; a guide still needs an assignment.
        $this->google('stranger@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/es/mis-guias');

        $stranger = User::firstWhere('email', 'stranger@gmail.com');
        $this->assertAuthenticatedAs($stranger);
        $this->assertSame('reader', $stranger->membership->role->alias);
        $this->assertNull($stranger->approved_at);

        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->has('guides', 0));
        $this->get($this->link())->assertOk()->assertInertia(fn ($page) => $page
            ->component('GuidePresentation')
            ->where('state', 'closed')
            ->missing('stages'));
    }

    public function test_google_takes_over_an_unverified_account_registered_with_the_same_email(): void
    {
        // Someone registered this address first, with a password of their own, and never verified it.
        $squatter = User::factory()->unverified()->create(['email' => 'ana@gmail.com']);
        $squatter->membership()->create(['role_id' => Role::where('alias_number', Role::ALIAS_READER)->first()->id]);
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $this->assertNull($reader->user_id);

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback');

        $ana = $squatter->fresh();
        $this->assertAuthenticatedAs($ana);
        $this->assertNotNull($ana->email_verified_at);
        // The squatter's password no longer opens it.
        $this->assertFalse(Hash::check('password', $ana->password));
        $this->assertSame($ana->id, $reader->fresh()->user_id);
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $this->google('ana@gmail.com', verified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_account_without_a_membership_assigned_a_guide_becomes_a_reader_and_stays_unapproved(): void
    {
        $client = User::factory()->create([
            'email' => 'ana@gmail.com',
            'approved_at' => null,
        ]);
        $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($client);
        $this->assertSame(1, User::count());
        $this->assertSame('reader', $client->fresh()->membership->role->alias);
        $this->assertNull($client->fresh()->approved_at);
    }

    public function test_the_owner_may_sign_in_with_google_and_lands_in_the_editor(): void
    {
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['email' => 'owner@gmail.com', 'approved_at' => now()]);

        $this->google('owner@gmail.com');
        $this->get('/auth/google/callback')->assertRedirect('/es/editor');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_a_reader_removed_from_every_guide_can_still_sign_in(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);
        $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $reader->id])->delete();

        $this->google('ana@gmail.com');
        $this->get('/auth/google/callback')->assertRedirect('/es/mis-guias');

        $this->assertAuthenticatedAs($reader);
    }

    public function test_a_failed_google_callback_signs_nobody_in(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── The link, by account ─────────────────────────────────────────────────────────────────

    public function test_a_removed_reader_is_told_it_is_no_longer_available_and_given_none_of_its_content(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->guide->readers()->create(['email' => $reader->email, 'user_id' => $reader->id])->delete();

        $this->actingAs($reader)->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GuidePresentation')
                ->where('state', 'removed')
                ->where('accessUrl', null)
                ->where('myGuidesUrl', '/es/mis-guias')
                // What it is about, since ADR 0022; never what is in it.
                ->where('guide.title', 'Excel para el trabajo')
                ->missing('stages')
                ->missing('sections')
                ->missing('version'));
    }

    public function test_another_account_is_told_it_is_not_shared_and_may_ask_once_while_it_is_open(): void
    {
        Queue::fake();
        $other = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']);

        // Closed for enrollment, as every guide starts: "not open yet", and nothing to ask.
        $this->actingAs($other)->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GuidePresentation')
                ->where('state', 'closed')
                ->where('email', 'bob@gmail.com')
                ->where('accessUrl', null)
                ->missing('stages'));
        $this->post($this->link().'/acceso')->assertForbidden();

        $this->guide->update(['open_for_enrollment' => true]);
        $this->get($this->link())->assertInertia(fn ($page) => $page->where('state', 'open'));

        $this->post($this->link().'/acceso', ['message' => 'Hola'])->assertRedirect();
        $this->post($this->link().'/acceso', ['message' => 'Otra vez'])->assertRedirect();

        $this->assertSame(1, AccessRequest::count());
        $this->assertSame('Hola', AccessRequest::sole()->message);
        $this->get($this->link())->assertInertia(fn ($page) => $page->where('state', 'pending'));
    }

    public function test_the_owner_approves_a_request_and_the_account_becomes_a_reader(): void
    {
        $other = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']);
        $request = $this->guide->accessRequests()->create(['user_id' => $other->id]);
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->get("/es/editor/guides/{$this->guide->id}/readers")
            ->assertInertia(fn ($page) => $page->where('accessRequests.0.email', 'bob@gmail.com'));

        $this->post("/es/editor/guides/{$this->guide->id}/access-requests/{$request->id}")->assertRedirect();

        // Kept, answered: the editor counts it (ADR 0022).
        $this->assertSame(AccessRequestStatus::APPROVED, $request->fresh()->status);
        $this->assertSame($owner->id, $request->fresh()->decided_by);
        $this->assertSame($other->id, $this->guide->readers()->firstWhere('email', 'bob@gmail.com')->user_id);

        $this->actingAs($other)->get($this->link())->assertOk();
    }

    public function test_the_owner_declines_a_request_and_the_account_is_told_it_has_no_access(): void
    {
        $other = User::factory()->withRole(Role::ALIAS_READER)->create();
        $request = $this->guide->accessRequests()->create(['user_id' => $other->id]);
        $this->guide->update(['open_for_enrollment' => true]);
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->delete("/es/editor/guides/{$this->guide->id}/access-requests/{$request->id}")->assertRedirect();

        $this->assertSame(AccessRequestStatus::DECLINED, $request->fresh()->status);
        $this->assertSame(0, $this->guide->readers()->count());

        // A decline stands: no access, and nothing more to ask.
        $this->actingAs($other)->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('state', 'declined')->where('accessUrl', null));
        $this->post($this->link().'/acceso')->assertRedirect();
        $this->assertSame(AccessRequestStatus::DECLINED, $request->fresh()->status);
    }

    public function test_adding_a_removed_reader_back_restores_them_with_their_colours(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $reader->paint(Palette::FOREST, Accent::AMBER, Reader::BY_READER);
        $reader->delete();
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->post("/es/editor/guides/{$this->guide->id}/readers", ['email' => 'ana@gmail.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Reader::withTrashed()->count());
        $this->assertFalse($reader->fresh()->trashed());
        $this->assertSame(Palette::FOREST, $reader->fresh()->palette);
    }
}
