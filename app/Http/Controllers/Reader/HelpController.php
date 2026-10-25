<?php

namespace App\Http\Controllers\Reader;

use App\Services\ArcadiaSite;
use App\Services\Devices;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A reader's help: short answers on how Arcadia works — opening a guide, progress, notes, colours,
 * devices, the account — and the address to write to. The answers are copy (`arcadia.help`); only
 * what differs per account or per deployment is sent: the device limit and the contact address.
 */
class HelpController
{
    public function __invoke(Request $request, ArcadiaSite $site, Devices $devices): Response
    {
        return Inertia::render('reader/Help', [
            'contactEmail' => $site->contactEmail(),
            'deviceLimit' => $devices->limitFor($request->user()),
            'pageTitle' => __('arcadia.help.meta.title'),
            'noIndex' => true,
        ]);
    }
}
