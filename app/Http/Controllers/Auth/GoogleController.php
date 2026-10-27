<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AdmitGoogleAccount;
use App\Services\ArcadiaSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with Google" — the way readers sign in.
 *
 * It keeps Socialite's `state` check (not stateless), so a callback this browser did not start is
 * refused. Who may come in is `AdmitGoogleAccount`'s, asked before anything is written; then it lands where they meant to
 * go — the guide they tapped — else their own home. The sign-in also counts as a password
 * confirmation for the usual window, so a reader can add a passkey without a password.
 *
 * Google's console needs the callback as an authorised redirect URI:
 * `https://arcadia.yhndevside.com/auth/google/callback`.
 */
class GoogleController
{
    public function redirect(ArcadiaSite $site): SymfonyRedirect
    {
        try {
            // Always the account picker: a phone holds several Google accounts, and the one a
            // guide was shared with is often not the one Google would choose by itself.
            return $this->driver($site)
                ->with(['prompt' => 'select_account'])
                ->redirect();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', __('arcadia.auth.google.unavailable'));
        }
    }

    public function callback(Request $request, ArcadiaSite $site, AdmitGoogleAccount $admit): RedirectResponse
    {
        try {
            $google = $this->driver($site)->user();
        } catch (Throwable $e) {
            // A cancelled consent, a stale or forged state, Google unreachable.
            return redirect()->route('login')->with('error', __('arcadia.auth.google.failed'));
        }

        $user = $admit->handle($google, $site);

        if (! $user) {
            return redirect()->route('login')->with('error', __('arcadia.auth.google.not_shared', ['email' => $google->getEmail()]));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Google stands in for a password confirmation: a Google-made account's password is
        // random and known to nobody, so without this it could never add a passkey.
        $request->session()->passwordConfirmed();

        // The page they land on offers a passkey, so the next sign-in skips Google. Once, and
        // never to an account that already has one; whether this device can hold one is the
        // browser's to say (`PasskeyOffer.vue`).
        if (Features::enabled(Features::passkeys()) && ! $user->hasPasskeysEnabled()) {
            $request->session()->flash('offer_passkey', true);
        }

        return redirect()->intended($site->homeAfterSignIn($user));
    }

    private function driver(ArcadiaSite $site): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirectUrl($site->url('auth/google/callback'));
    }
}
