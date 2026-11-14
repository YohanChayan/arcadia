<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\DateRangeRequest;
use App\Services\Site\AgentActivity;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site settings → Agents: what the sales assistant and the knowledge index have been doing, as
 * counts and metrics — never what was said. Site-wide: both editors show the same.
 */
class SiteAgentsController
{
    public function __invoke(DateRangeRequest $request, AgentActivity $agents): Response
    {
        $range = $request->range('agents');

        return Inertia::render('editor/settings/Agents', [
            'assistant' => $agents->assistant($range['start'], $range['end']),
            'knowledge' => $agents->knowledge(),
            // Echoed back so the filter opens on what the URL asked for.
            'filter' => $request->shown('agents'),
        ]);
    }
}
