<?php

namespace App\Actions;

use App\Actions\Auth\HandleSocialAuthUser;
use App\Models\AuthProvider;
use App\Models\User;
use App\Services\ArcadiaSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Who a Google sign-in on Arcadia lets in.
 *
 * **Anyone whose Google email is verified** (2026-10-15): an account with no guide sees an empty
 * "My guides", and a guide still opens only for an assigned reader. Refused: an unverified email,
 * and an account Arcadia already knows but does not admit (an unapproved owner).
 *
 * A first-time account is made through `HandleSocialAuthUser`, given a reader membership of
 * Arcadia, and linked to its assignments. **Never `approved_at`**: approval is the owner's.
 *
 * **An unverified account with the same email is taken over, not joined.** Anyone may register
 * any address; if they registered this one first, its password is theirs. Google has now proved
 * who owns the address, so that password is replaced and its sessions ended before the account
 * becomes this person's.
 */
class AdmitGoogleAccount
{
    public function __construct(
        private readonly HandleSocialAuthUser $users,
        private readonly LinkReaderAssignments $assignments,
    ) {}

    public function handle(SocialiteUser $google, ArcadiaSite $site): ?User
    {
        $email = mb_strtolower(trim((string) $google->getEmail()));

        // Google says whether it verified the address; an unverified one could be anyone's.
        if ($email === '' || ! ($google->getRaw()['email_verified'] ?? false)) {
            return null;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing && $site->roleOf($existing) !== null && ! $site->admits($existing)) {
            return null;
        }

        return DB::transaction(function () use ($google, $site, $existing) {
            if ($existing && ! $existing->hasVerifiedEmail()) {
                $existing->forceFill(['password' => Str::random(40), 'remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $existing->id)->delete();
            }

            $user = $this->users->handle($google, AuthProvider::where('alias', 'google')->firstOrFail());

            $site->registered($user);
            $this->assignments->handle($user->refresh());

            return $user;
        });
    }
}
