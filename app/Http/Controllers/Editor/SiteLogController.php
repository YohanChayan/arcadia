<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\DateRangeRequest;
use App\Services\Site\ErrorLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site settings → Logs: the errors this environment wrote to `laravel.log`, newest first, in a
 * date window. Site-wide: both editors show the same. Its own route, so the price form and the
 * Health tab never wait on the scan of a multi-megabyte file.
 */
class SiteLogController
{
    public function __invoke(DateRangeRequest $request, ErrorLog $log): Response
    {
        $range = $request->range('log');

        return Inertia::render('editor/settings/Logs', [
            'log' => $log->errors($range['start'], $range['end']),
            // Echoed back so the filter opens on what the URL asked for.
            'filter' => $request->shown('log'),
        ]);
    }
}
