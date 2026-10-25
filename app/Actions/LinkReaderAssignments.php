<?php

namespace App\Actions;

use App\Models\Reader;
use App\Models\User;

/**
 * Links the guides assigned to an email to the account that has proved it owns that email.
 *
 * **Only a verified account.** Anyone may register on Arcadia (2026-10-15), so an account's email
 * is only a claim until it is verified — linking before that would hand a guide to whoever typed
 * its reader's address first. Run when an email is verified (the `Verified` event) and when
 * Google signs someone in (Google verified it); `AssignReader` links at assignment only an
 * account already verified.
 */
class LinkReaderAssignments
{
    public function handle(User $user): void
    {
        if (! $user->hasVerifiedEmail() || ! $user->email) {
            return;
        }

        Reader::withTrashed()
            ->where('email', mb_strtolower($user->email))
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }
}
