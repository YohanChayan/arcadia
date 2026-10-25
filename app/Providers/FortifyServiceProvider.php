<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\PasskeySignedInResponse;
use App\Http\Responses\SignedInResponse;
use App\Http\Responses\VerifiedEmailResponse;
use App\Models\User;
use App\Services\ArcadiaSite;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Where a sign-in lands is the site's decision, not `fortify.home`'s: a reader in their
        // guides, the owner in the editor. Bound here rather than in `register()`, because
        // Fortify binds its own responses there and could win.
        $this->app->singleton(LoginResponse::class, SignedInResponse::class);
        $this->app->singleton(RegisterResponse::class, SignedInResponse::class);
        $this->app->singleton(VerifyEmailResponse::class, VerifiedEmailResponse::class);
        $this->app->singleton(PasskeyLoginResponse::class, PasskeySignedInResponse::class);

        // What Arcadia gives a new account — a reader membership.
        Event::listen(Registered::class, function (Registered $event) {
            if ($event->user instanceof User) {
                app(ArcadiaSite::class)->registered($event->user);
            }
        });

        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Fortify's own check, plus Arcadia's admission: an owner or a reader. A refused user
        // fails exactly as a wrong password does — same message, same throttle — so the form
        // says nothing about which accounts exist.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return app(ArcadiaSite::class)->admits($user) ? $user : null;
        });

        // A passkey signs in without `authenticateUsing` (the package logs the user in itself),
        // so admission is asked again here: a passkey registered before a membership was removed
        // must not outlive it.
        Passkeys::authorizeLoginUsing(fn (Request $request, PasskeyUser $user): bool => $user instanceof User
            && app(ArcadiaSite::class)->admits($user));
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $site = app(ArcadiaSite::class);

            return Inertia::render($site->loginPage(), [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister' => Features::enabled(Features::registration()),
                'canUsePasskeys' => Features::enabled(Features::passkeys()),
                'status' => $request->session()->get('status'),
            ] + $site->loginProps());
        });

        // The other account screens are Arcadia's own pages.
        $screen = function (string $screen, array $props = []) {
            [$page, $own] = app(ArcadiaSite::class)->authPage($screen);

            return Inertia::render($page, $props + $own);
        };

        Fortify::resetPasswordView(fn (Request $request) => $screen('reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => $screen('forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => $screen('verify-email', [
            'status' => $request->session()->get('status'),
            'email' => $request->user()?->email,
        ]));

        Fortify::registerView(fn () => $screen('register'));

        // The kit's page: asked before a passkey is added or removed in Settings.
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword', [
            'canUsePasskeys' => Features::enabled(Features::passkeys()),
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
