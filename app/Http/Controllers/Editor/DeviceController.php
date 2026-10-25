<?php

namespace App\Http\Controllers\Editor;

use App\Models\Device;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's side of a reader's devices (option D), from a guide's Readers screen: revoke one —
 * the browser holding it is signed out on its next request (`RecogniseDevice`) — and set this
 * account's own limit, or clear it back to the site's.
 *
 * Per account, not per guide: a reader's devices are the same whichever guide they open.
 */
class DeviceController
{
    public function destroy(int $user, int $device): RedirectResponse
    {
        Device::query()->where('user_id', $this->member($user)->user_id)->findOrFail($device)->delete();

        return back()->with('success', __('arcadia.editor.devices.flash.revoked'));
    }

    public function limit(Request $request, int $user): RedirectResponse
    {
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        $this->member($user)->update(['device_limit' => $validated['limit'] ?? null]);

        return back()->with('success', __('arcadia.editor.devices.flash.limit'));
    }

    /** An Arcadia member only: the owner manages Arcadia's readers, not every account on the site. */
    private function member(int $user): Membership
    {
        return Membership::query()->where('user_id', $user)->firstOrFail();
    }
}
