<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\DateRangeRequest;
use App\Services\Site\SentMailLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site settings → Mail: the emails the site sent recently — when, to whom, the subject and which
 * mail — never their body. Site-wide: both editors show the same.
 */
class SiteMailController
{
    public function __invoke(DateRangeRequest $request, SentMailLog $log): Response
    {
        $range = $request->range('mail');

        return Inertia::render('editor/settings/Mail', [
            'sent' => $log->sent($range['start'], $range['end']),
            // Echoed back so the filter opens on what the URL asked for.
            'filter' => $request->shown('mail'),
        ]);
    }
}
