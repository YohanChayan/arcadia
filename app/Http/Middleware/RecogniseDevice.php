<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use App\Services\ArcadiaSite;
use App\Services\Devices;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every page a reader reads Arcadia through knows which of their devices it is (option D; the rules
 * are `Devices`'):
 *
 * - the device this session already knows, still on the account → on;
 * - one the session knew and the account no longer has — revoked by the owner, or swapped out by
 *   the reader elsewhere → signed out, to sign in again;
 * - a browser whose cookie names one of the account's devices → on;
 * - a new browser, with a slot free → it becomes a device;
 * - a new browser at the limit → the devices page, to swap one out or ask.
 *
 * Signed out, unverified (the page sends them to verify), or the site's owner: passed through.
 * The owner is never counted — the limit is about readers sharing a login.
 *
 * A guide's link runs it from its controller (`check()`), and only for a reader about to read it:
 * someone shown the guide's presentation is not reading Arcadia, and spends no slot on it.
 *
 * The session remembers the device with the account it belongs to, so a session that changes
 * account is never taken for a revoked device.
 */
class RecogniseDevice
{
    public const COOKIE = 'arcadia_device';

    private const SESSION_KEY = 'arcadia.device';

    // Five years: the cookie outlives every session, which is what makes it a device.
    private const COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function __construct(
        private readonly Devices $devices,
        private readonly ArcadiaSite $site,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->check($request) ?? $next($request);
    }

    /** Null when this request may go on — on a device of the account's — else where it goes instead. */
    public function check(Request $request): ?Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail() || $this->site->roleOf($user)?->alias === 'admin') {
            return null;
        }

        $known = self::sessionDevice($request, $user);
        $device = self::current($request, $user, $this->devices);

        if ($known !== null && ! $device) {
            return $this->signOut($request);
        }

        if ($device) {
            $this->devices->touch($device);
            self::remember($request, $user, $device);

            return null;
        }

        if ($this->devices->active($user)->count() < $this->devices->limitFor($user)) {
            self::remember($request, $user, ...$this->devices->register($user, (string) $request->userAgent()));

            return null;
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect($this->site->devicesPath());
    }

    /** The account's device this request is, if it is one yet. */
    public static function current(Request $request, User $user, Devices $devices): ?Device
    {
        $token = $request->cookie(self::COOKIE);

        return $devices->find($user, self::sessionDevice($request, $user), is_string($token) && $token !== '' ? $token : null);
    }

    /** This session is that device — and, given its token, so is this browser from now on. */
    public static function remember(Request $request, User $user, Device $device, ?string $token = null): void
    {
        $request->session()->put(self::SESSION_KEY, ['user' => $user->id, 'device' => $device->id]);

        if ($token !== null) {
            Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);
        }
    }

    /** The device this session was for this account; another account's is none of its business. */
    private static function sessionDevice(Request $request, User $user): ?int
    {
        $remembered = $request->session()->get(self::SESSION_KEY);

        return ($remembered['user'] ?? null) === $user->id ? $remembered['device'] : null;
    }

    private function signOut(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(self::COOKIE));

        return redirect()->route('login')->with('error', __('arcadia.devices.signed_out'));
    }
}
