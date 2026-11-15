<?php

namespace App\Actions\Readers;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\User;

/**
 * Makes an email a reader of a guide — from the Readers screen, or by accepting a request for
 * access. A reader removed earlier is restored, colours and all, rather than added twice; the
 * account that has verified the email is linked (an unverified one is linked when it verifies,
 * `LinkReaderAssignments`); and that account's request for this guide, if any, is answered by it
 * — approved, and kept, whether the owner approved it or added the email himself.
 * Whether they are told by email (`notify`) is the owner's choice; the telling is `TellReaders`.
 *
 * A name given is theirs on every guide of this language (`NameReader`); none keeps the one they
 * already have, here or on another guide.
 */
class AssignReader
{
    public function __construct(private NameReader $names) {}

    public function handle(Guide $guide, string $email, User $by, bool $notify = true, ?string $name = null): Reader
    {
        $email = mb_strtolower(trim($email));
        $name = filled($name) ? trim($name) : null;
        // Only an account that has verified this email: anyone may register any address.
        $userId = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('email_verified_at')->value('id');

        $reader = $guide->readers()->withTrashed()->firstWhere('email', $email);

        if ($reader) {
            $reader->restore();
            $reader->forceFill(['user_id' => $reader->user_id ?? $userId, 'assigned_by' => $by->id, 'notify' => $notify])->save();
        } else {
            $reader = $guide->readers()->create(['email' => $email, 'user_id' => $userId, 'assigned_by' => $by->id, 'notify' => $notify]);
        }

        $this->names->handle($reader, $name ?? $this->names->knownName($reader));
        $reader->refresh();

        if ($userId !== null) {
            AccessRequest::query()->where('guide_id', $guide->id)->where('user_id', $userId)
                ->where('status', '!=', AccessRequestStatus::APPROVED)
                ->first()?->decide(AccessRequestStatus::APPROVED, $by);
        }

        return $reader;
    }
}
