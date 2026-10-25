<?php

namespace App\Http\Controllers\Reader;

use App\Http\Middleware\RecogniseDevice;
use App\Models\Device;
use App\Services\ArcadiaSite;
use App\Services\Devices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where `RecogniseDevice` sends an account opening Arcadia on one browser too many: its devices,
 * and — once every thirty days — the choice of one to swap for this browser. Past that, it says
 * to write to the owner, who can free a slot or raise this account's limit.
 */
class DeviceController
{
    public function show(Request $request, ArcadiaSite $site, Devices $devices): Response|RedirectResponse
    {
        $user = $request->user();

        // Reached with a slot free, or on a device already theirs: nothing to choose.
        if (RecogniseDevice::current($request, $user, $devices) || $devices->active($user)->count() < $devices->limitFor($user)) {
            return redirect()->intended($site->readerPath());
        }

        return Inertia::render('reader/DeviceLimit', [
            'pageTitle' => __('arcadia.devices.title').' · Arcadia',
            'noIndex' => true,
            'email' => $user->email,
            'limit' => $devices->limitFor($user),
            'devices' => $devices->active($user)->map(fn (Device $device) => [
                'id' => $device->id,
                'label' => $device->label,
                'last_used_at' => $device->last_used_at,
            ]),
            'canReplace' => $devices->canReplace($user),
            'replaceUrl' => $site->devicesPath(),
            'contactEmail' => $site->contactEmail(),
        ]);
    }

    /** The reader's own swap: that device out, this browser in. */
    public function replace(Request $request, int $device, ArcadiaSite $site, Devices $devices): RedirectResponse
    {
        $user = $request->user();

        $out = Device::query()->where('user_id', $user->id)->active()->findOrFail($device);
        abort_unless($devices->canReplace($user), 403);

        RecogniseDevice::remember($request, $user, ...$devices->replace($user, $out, (string) $request->userAgent()));

        return redirect()->intended($site->readerPath());
    }
}
