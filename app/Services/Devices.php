<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * How many browsers one account may read Arcadia on (option D, D1): a device is recognised by a
 * cookie, a new one takes a free slot, and at the limit the reader swaps one out themselves —
 * once every thirty days — or asks the owner. A device unused for ninety days frees its slot.
 *
 * What it guards against is narrow: a reader handing over their login. A forwarded link is
 * already useless without the account.
 *
 * The rules only. Which device a request is — its session and its cookie — is read and written by
 * `RecogniseDevice`, the one that holds the request.
 */
class Devices
{
    public const REPLACE_EVERY_DAYS = 30;

    public function __construct(private readonly ArcadiaSite $site) {}

    public function membership(User $user): ?Membership
    {
        return $user->membership()->first();
    }

    /** The limit an account has when its membership sets none. */
    public function siteLimit(): int
    {
        return $this->site->deviceLimit();
    }

    /** The account's own limit, else the site's. */
    public function limitFor(User $user): int
    {
        return $this->membership($user)?->device_limit ?? $this->siteLimit();
    }

    /** @return Collection<int, Device> the devices holding a slot, most recently used first */
    public function active(User $user): Collection
    {
        return Device::query()->where('user_id', $user->id)->active()->latest('last_used_at')->get();
    }

    /** May the reader swap a device out themselves now? Once every thirty days. */
    public function canReplace(User $user): bool
    {
        $replacedAt = $this->membership($user)?->devices_replaced_at;

        return $replacedAt === null || $replacedAt->lte(now()->subDays(self::REPLACE_EVERY_DAYS));
    }

    /** One of this account's devices: the one the session already knew, else the one its cookie names. */
    public function find(User $user, ?int $sessionDevice, ?string $cookieToken): ?Device
    {
        $query = Device::query()->where('user_id', $user->id);

        if ($sessionDevice !== null) {
            return $query->whereKey($sessionDevice)->first();
        }

        return $cookieToken
            ? $query->where('token_hash', hash('sha256', $cookieToken))->first()
            : null;
    }

    /** Mark it used — at most every ten minutes, not on every request. */
    public function touch(Device $device): void
    {
        if ($device->last_used_at->lt(now()->subMinutes(10))) {
            $device->update(['last_used_at' => now()]);
        }
    }

    /**
     * A browser becomes one of the account's devices.
     *
     * @return array{Device, string} the device, and the token its cookie must carry — kept nowhere else
     */
    public function register(User $user, string $userAgent): array
    {
        // Idle devices hold no slot; forget them rather than keep a list of old browsers.
        Device::query()->where('user_id', $user->id)
            ->where('last_used_at', '<', now()->subDays(Device::IDLE_DAYS))
            ->delete();

        $token = Str::random(64);

        $device = Device::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'label' => self::label($userAgent),
            'last_used_at' => now(),
        ]);

        return [$device, $token];
    }

    /**
     * At the limit, the reader's own swap: that device out, this browser in, the month's swap spent.
     *
     * @return array{Device, string} as `register()`
     */
    public function replace(User $user, Device $out, string $userAgent): array
    {
        return DB::transaction(function () use ($user, $out, $userAgent) {
            $out->delete();
            $this->membership($user)?->update(['devices_replaced_at' => now()]);

            return $this->register($user, $userAgent);
        });
    }

    /** "Chrome · Android": enough to tell one's own devices apart, nothing more. */
    public static function label(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'WhatsApp') => 'WhatsApp',
            str_contains($userAgent, 'Instagram') => 'Instagram',
            str_contains($userAgent, 'FBAN') || str_contains($userAgent, 'FBAV') => 'Facebook',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox') || str_contains($userAgent, 'FxiOS') => 'Firefox',
            str_contains($userAgent, 'Chrome') || str_contains($userAgent, 'CriOS') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => null,
        };

        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'CrOS') => 'Chromebook',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return implode(' · ', array_filter([$browser, $system])) ?: __('arcadia.devices.unknown');
    }
}
