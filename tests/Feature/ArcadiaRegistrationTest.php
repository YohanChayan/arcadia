<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Email-and-password accounts on Arcadia, the second way in beside Google (2026-10-15): anyone may
 * register, every account email links back to Arcadia's host, and nothing of a guide is read —
 * nor any assignment linked — until the email is verified.
 */
class ArcadiaRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        // Absolute links take their scheme from `APP_URL`; pin production's.
        config(['app.url' => 'https://arcadia.yhndevside.com']);
        Role::firstOrCreate(['alias_number' => Role::ALIAS_READER], ['name' => 'Reader']);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();
    }

    private function register(string $email = 'ana@gmail.com'): TestResponse
    {
        return $this->post('/register', [
            'name' => 'Ana', 'email' => $email, 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ]);
    }

    public function test_arcadia_renders_its_own_account_screens(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page->component('auth/Register')->where('noIndex', true));
        $this->get('/forgot-password')->assertOk()->assertInertia(fn ($page) => $page->component('auth/ForgotPassword'));
        $this->get('/reset-password/abc?email=ana@gmail.com')->assertOk()->assertInertia(fn ($page) => $page
            ->component('auth/ResetPassword')->where('token', 'abc'));
    }

    public function test_anyone_registers_as_a_reader_never_approved_and_is_asked_to_verify(): void
    {
        Notification::fake();

        $this->register()->assertRedirect('/es/mis-guias');

        $ana = User::firstWhere('email', 'ana@gmail.com');
        $this->assertAuthenticatedAs($ana);
        $this->assertSame('reader', $ana->membership->role->alias);
        $this->assertNull($ana->approved_at);

        // Their guides wait behind the email.
        $this->get('/es/mis-guias')->assertRedirect('/email/verify');
        $this->get('/email/verify')->assertOk()->assertInertia(fn ($page) => $page
            ->component('auth/VerifyEmail')->where('email', 'ana@gmail.com'));

        Notification::assertSentTo($ana, QueuedVerifyEmail::class);
    }

    public function test_the_verification_link_opens_on_arcadia_and_returns_to_the_guide(): void
    {
        Notification::fake();
        // The signature covers the scheme; the test client speaks http, so sign for http.
        config(['app.url' => 'http://arcadia.yhndevside.com']);
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        // From the guide's link: signed out, its page offers sign-in, then registered.
        $this->get('/guia/'.$this->guide->link_token.'?signin=1')->assertRedirect('/login');
        $this->register();
        $ana = User::firstWhere('email', 'ana@gmail.com');

        // Unverified: no guide, and no assignment linked yet — the address is only a claim.
        $this->get('/guia/'.$this->guide->link_token)->assertRedirect('/email/verify');
        $this->assertNull($reader->fresh()->user_id);

        $url = null;
        Notification::assertSentTo($ana, QueuedVerifyEmail::class, function (QueuedVerifyEmail $mail) use ($ana, &$url) {
            $url = $mail->toMail($ana)->actionUrl;

            return true;
        });

        $this->assertStringStartsWith('http://arcadia.yhndevside.com/email/verify/', $url);

        // The signed link, on the host it was signed for: back to the guide they were going to.
        $this->get($url)->assertRedirect('/guia/'.$this->guide->link_token);
        $this->assertTrue($ana->fresh()->hasVerifiedEmail());
        $this->assertSame($ana->id, $reader->fresh()->user_id);

        $this->get('/guia/'.$this->guide->link_token)->assertOk()->assertInertia(fn ($page) => $page->component('Guide'));
    }

    public function test_an_unverified_account_is_never_linked_at_assignment(): void
    {
        $squatter = User::factory()->unverified()->create(['email' => 'ana@gmail.com']);
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->post("/es/editor/guides/{$this->guide->id}/readers", ['email' => 'ana@gmail.com']);

        $this->assertNull($this->guide->readers()->first()->user_id);
        $this->assertNotSame($squatter->id, $this->guide->readers()->first()->user_id);
    }

    public function test_a_registered_reader_signs_in_with_their_password(): void
    {
        $ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com', 'approved_at' => null]);

        $this->post('/login', ['email' => 'ana@gmail.com', 'password' => 'password'])
            ->assertRedirect('/es/mis-guias');

        $this->assertAuthenticatedAs($ana);
    }

    public function test_the_reset_link_opens_on_arcadia(): void
    {
        Notification::fake();
        $ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);

        $this->post('/forgot-password', ['email' => 'ana@gmail.com'])->assertSessionHasNoErrors();

        Notification::assertSentTo($ana, QueuedResetPassword::class, function (QueuedResetPassword $mail) use ($ana) {
            // The scheme is the request's (https in production); the host is the point.
            return str_contains($mail->toMail($ana)->actionUrl, '://arcadia.yhndevside.com/reset-password/');
        });
    }
}
