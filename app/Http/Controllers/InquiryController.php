<?php

namespace App\Http\Controllers;

use App\Http\Requests\InquiryRequest;
use App\Jobs\SendInquiryNotificationJob;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;

/**
 * Someone asks for a guide, from the landing page's popup. No account is needed — asking is free
 * (decided 2026-10-16) — but a signed-in, verified one is recorded with the request, so the owner
 * sees which account to assign the guide to. An unverified email is only a claim, and nothing
 * links to one (option L9). Stored in the page's language and mailed to the owner; the
 * popup thanks them once the redirect lands.
 */
class InquiryController
{
    public function __invoke(InquiryRequest $request): RedirectResponse
    {
        $inquiry = Inquiry::create([
            'user_id' => $request->user()?->hasVerifiedEmail() ? $request->user()->id : null,
            'locale' => app()->getLocale(),
            ...$request->safe()->only(['topic', 'contact']),
        ]);

        // The row is already written: a mail outage must not turn it into an error page (the
        // rule in `docs/architecture/README.md`). `Bus::dispatch`, not `::dispatch()`, so the
        // job is sent inside `rescue` rather than in a destructor after it.
        rescue(fn () => Bus::dispatch(new SendInquiryNotificationJob($inquiry)));

        return back();
    }
}
