<?php

namespace App\Http\Controllers;

use App\Services\ArcadiaSite;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Arcadia's privacy note: what a reader's Google account gives us and what is kept with it, what
 * stays on their device, the three technical cookies, and what YouTube does once a reader presses
 * play (doubt 12). Short, because the product keeps little — no analytics tool. **It must change
 * with what Arcadia keeps**: progress on the account, the device record, the phone on the request
 * form, an enrollment request's message and the per-day count of a guide page's visits (ADR 0022)
 * each rewrite a section of it.
 */
class PrivacyController
{
    public function __invoke(ArcadiaSite $site): Response
    {
        return Inertia::render('Privacy', [
            'pageTitle' => __('arcadia.privacy.title'),
            'noIndex' => true,
            'canonicalUrl' => $site->url($site->privacyPath()),
            'email' => $site->contactEmail(),
        ]);
    }
}
