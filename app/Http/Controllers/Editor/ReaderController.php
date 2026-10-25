<?php

namespace App\Http\Controllers\Editor;

use App\Actions\AssignReader;
use App\Actions\TellReaders;
use App\Enums\Accent;
use App\Enums\AccessRequestStatus;
use App\Enums\Palette;
use App\Http\Requests\ReaderRequest;
use App\Models\AccessRequest;
use App\Models\Device;
use App\Models\Guide;
use App\Models\Membership;
use App\Models\Reader;
use App\Services\Appearance;
use App\Services\Devices;
use App\Services\GuideProgressSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A guide's readers: who it is for, by email. Adding one is the permission to read it once the
 * reader signs in; removing one takes it away. Each row carries the reader's own colours and who
 * last saved them, and — once they have an account — their devices, device limit and where they
 * stand on the released version (`GuideProgressSummary`, as their own dashboard counts it).
 */
class ReaderController
{
    public function index(Guide $guide, Devices $devices, GuideProgressSummary $summary): Response
    {
        $this->ownLocale($guide);

        // Progress is counted against what readers read: nothing to count until a release.
        $released = $guide->releasedVersion();

        $readers = $guide->readers()->with('user:id,name')->oldest()->get();
        $accounts = $readers->pluck('user_id')->filter();
        $devicesOf = Device::query()->whereIn('user_id', $accounts)->active()->latest('last_used_at')->get()->groupBy('user_id');
        $limitOf = Membership::query()->whereIn('user_id', $accounts)->pluck('device_limit', 'user_id');

        return Inertia::render('editor/guides/Readers', [
            'guide' => [
                ...$guide->only('id', 'title'),
                'palette' => ($guide->default_palette ?? Palette::ARCADIA)->value,
                'accent' => ($guide->default_accent ?? Accent::OWN)->value,
            ],
            'appearanceOptions' => Appearance::options(),
            // The site's device limit, which a reader's own overrides.
            'deviceLimit' => $devices->siteLimit(),
            // Whether a reader can open it now — an email is sent only then.
            'readable' => $guide->isReadable(),
            'released' => $released !== null,
            'readers' => $readers
                ->map(fn (Reader $reader) => [
                    'id' => $reader->id,
                    'user_id' => $reader->user_id,
                    'email' => $reader->email,
                    'name' => $reader->name,
                    // What the guide will call them: the name above, else their account's first name.
                    'display_name' => $reader->displayName(),
                    // An account with this email exists — they can sign in once Google is wired.
                    'has_account' => $reader->user_id !== null,
                    'palette' => ($reader->palette ?? Palette::ARCADIA)->value,
                    'accent' => ($reader->accent ?? Accent::OWN)->value,
                    // The badge: who last saved these colours, or null — untouched since assigned.
                    'appearance_by' => $reader->appearance_by,
                    'appearance_at' => $reader->appearance_at,
                    'created_at' => $reader->created_at,
                    // Told by email: when, or waiting for the guide to open, or not at all.
                    'notify' => $reader->notify,
                    'notified_at' => $reader->notified_at,
                    'devices' => ($devicesOf[$reader->user_id] ?? collect())->map(fn (Device $device) => [
                        'id' => $device->id,
                        'label' => $device->label,
                        'created_at' => $device->created_at,
                        'last_used_at' => $device->last_used_at,
                    ])->values(),
                    // Their own limit, or null for the site's.
                    'device_limit' => $limitOf[$reader->user_id] ?? null,
                    // Null without an account or a release; else done of total, and the last tick.
                    'progress' => $released && $reader->user_id
                        ? collect($summary->for($reader, $released))->only('done', 'total', 'stages', 'last_ticked_at')
                        : null,
                ]),
            // Whether accounts it is not shared with may ask to enroll from its link (ADR 0022).
            'openForEnrollment' => $guide->open_for_enrollment,
            // Enrollment requests: the pending first, then the answered, newest first. The note is
            // the owner's record of the payment; the requester never sees it.
            'accessRequests' => $guide->accessRequests()
                ->with('user:id,name,email')
                ->orderByRaw('status = ? desc', [AccessRequestStatus::PENDING->value])
                ->latest()
                ->get()
                ->map(fn (AccessRequest $request) => [
                    'id' => $request->id,
                    'name' => $request->user->name,
                    'email' => $request->user->email,
                    'status' => $request->status->value,
                    'message' => $request->message,
                    'owner_note' => $request->owner_note,
                    'created_at' => $request->created_at,
                    'decided_at' => $request->decided_at,
                ]),
            'noteMax' => AccessRequest::MAX_MESSAGE,
        ]);
    }

    public function store(ReaderRequest $request, Guide $guide, AssignReader $assign, TellReaders $tell): RedirectResponse
    {
        $this->ownLocale($guide);
        $email = $request->validated('email');

        $reader = $assign->handle($guide, $email, $request->user(), $request->boolean('notify', true), $request->validated('name'));

        return back()->with('success', __($this->addedFlash($reader, $tell->reader($reader)), ['email' => $email]));
    }

    /** What the guide calls this reader; blank goes back to their account's first name. */
    public function rename(Request $request, Guide $guide, Reader $reader): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($reader->guide_id === $guide->id, 404);

        $name = $request->validate(['name' => ['nullable', 'string', 'max:80']])['name'] ?? null;
        $reader->update(['name' => filled($name) ? trim($name) : null]);

        return back()->with('success', __('arcadia.editor.readers.flash.renamed'));
    }

    /** "Send again" — the reader says it never arrived. */
    public function notify(Guide $guide, Reader $reader, TellReaders $tell): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($reader->guide_id === $guide->id, 404);

        return $tell->again($reader)
            ? back()->with('success', __('arcadia.editor.readers.flash.resent', ['email' => $reader->email]))
            : back()->with('error', __('arcadia.editor.readers.flash.not_readable'));
    }

    /**
     * An enrollment request, approved — once the owner has arranged payment: its email becomes a
     * reader, told by the "you now have access" mail, and the request is kept as approved.
     */
    public function accept(Request $request, Guide $guide, AccessRequest $accessRequest, AssignReader $assign, TellReaders $tell): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($accessRequest->guide_id === $guide->id && $accessRequest->isPending(), 404);

        $email = $accessRequest->user->email;
        $reader = $assign->handle($guide, $email, $request->user());

        return back()->with('success', __($this->addedFlash($reader, $tell->reader($reader, accessGranted: true)), ['email' => $email]));
    }

    /** Declined, and kept: the guide's link then tells them they have no access, and asks nothing. */
    public function dismiss(Request $request, Guide $guide, AccessRequest $accessRequest): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($accessRequest->guide_id === $guide->id && $accessRequest->isPending(), 404);

        $accessRequest->decide(AccessRequestStatus::DECLINED, $request->user());

        return back()->with('success', __('arcadia.editor.readers.flash.dismissed'));
    }

    /** The owner's own note on a request — his record of the payment. Blank clears it. */
    public function note(Request $request, Guide $guide, AccessRequest $accessRequest): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($accessRequest->guide_id === $guide->id, 404);

        $note = $request->validate(['owner_note' => ['nullable', 'string', 'max:'.AccessRequest::MAX_MESSAGE]])['owner_note'] ?? null;
        $accessRequest->forceFill(['owner_note' => filled($note) ? trim($note) : null])->save();

        return back()->with('success', __('arcadia.editor.readers.flash.note_saved'));
    }

    /** Open or close the guide for enrollment requests from its link. */
    public function enrollment(Request $request, Guide $guide): RedirectResponse
    {
        $this->ownLocale($guide);

        $open = $request->validate(['open' => ['required', 'boolean']])['open'];
        $guide->forceFill(['open_for_enrollment' => $open])->save();

        return back()->with('success', __($open ? 'arcadia.editor.readers.flash.enrollment_opened' : 'arcadia.editor.readers.flash.enrollment_closed'));
    }

    public function destroy(Guide $guide, Reader $reader): RedirectResponse
    {
        $this->ownLocale($guide);
        abort_unless($reader->guide_id === $guide->id, 404);

        // Kept, marked removed: their guide then tells them it is no longer available to them.
        $reader->delete();

        return back()->with('success', __('arcadia.editor.readers.flash.removed', ['email' => $reader->email]));
    }

    /** What adding said: told now, told once the guide can be opened, or not told. */
    private function addedFlash(Reader $reader, bool $sent): string
    {
        return match (true) {
            $sent => 'arcadia.editor.readers.flash.added_and_told',
            $reader->notify && $reader->notified_at === null => 'arcadia.editor.readers.flash.added_waiting',
            default => 'arcadia.editor.readers.flash.added',
        };
    }

    /** A guide of the other language is not this editor's, whatever the id in the URL. */
    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }
}
