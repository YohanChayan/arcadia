<?php

namespace App\Http\Controllers;

use App\Enums\GuideStatus;
use App\Jobs\SendAccessRequestNotificationJob;
use App\Models\AccessRequest;
use App\Models\Guide;
use App\Services\ArcadiaSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A signed-in account asks to enroll in a guide not shared with it — from the guide's link, while
 * the owner has it open for enrollment (ADR 0022). Once per guide: asking again changes nothing,
 * and a request he declined stands. The owner is emailed; he arranges payment with them outside
 * Arcadia and answers on the guide's Readers screen. Nothing is granted until he approves.
 */
class AccessRequestController
{
    public function __invoke(Request $request, string $token, ArcadiaSite $site): RedirectResponse
    {
        $guide = Guide::query()
            ->where('link_token', $token)
            ->where('locale', $site->locale())
            ->where('status', GuideStatus::PUBLISHED)
            ->firstOrFail();

        $message = $request->validate([
            'message' => ['nullable', 'string', 'max:'.AccessRequest::MAX_MESSAGE],
        ])['message'] ?? null;

        $user = $request->user();

        // A reader, or one removed from it, has nothing to ask; a closed guide takes no requests.
        abort_if($guide->readers()->withTrashed()->where('user_id', $user->id)->exists(), 403);
        abort_unless($guide->open_for_enrollment, 403);

        $accessRequest = $guide->accessRequests()->firstOrCreate(
            ['user_id' => $user->id],
            ['message' => filled($message) ? trim($message) : null],
        );

        if ($accessRequest->wasRecentlyCreated) {
            SendAccessRequestNotificationJob::dispatch($accessRequest);
        }

        return back()->with('success', __('arcadia.presentation.flash.requested'));
    }
}
