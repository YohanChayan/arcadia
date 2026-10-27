<?php

namespace App\Http\Controllers;

use App\Enums\Accent;
use App\Enums\AccessRequestStatus;
use App\Enums\GuideStatus;
use App\Enums\Palette;
use App\Enums\PhraseMoment;
use App\Http\Middleware\RecogniseDevice;
use App\Models\AccessRequest;
use App\Models\Guide;
use App\Models\Note;
use App\Models\Reader;
use App\Models\Version;
use App\Services\Appearance;
use App\Services\ArcadiaSite;
use App\Services\GuidePresentation;
use App\Services\GuidePresenter;
use App\Services\GuideProgressSummary;
use App\Services\GuideVisits;
use App\Services\MotivationPicker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guide, opened by its link — the catalogue's cards open it too, while it is open for enrollment
 * (ADR 0023).
 *
 * Only a published guide of this site's language, and only its latest released version: a draft
 * is never shown to a reader. Anything else — a wrong or rotated token, a draft, the other
 * language's guide — is the same 404, so a link says nothing about what else exists.
 *
 * **Read only by its readers, signed in** (2026-10-15). The link is the guide's address, not
 * its permission. **Anyone else is shown what it is about** (ADR 0022, 2026-10-18), on the
 * presentation page (`GuidePresentation`) — its cover, title, goals and pitch, none of its content
 * — which says, by `state`:
 *
 * - signed out → `sign_in`: sign in to enroll while it is open, or to read it as one of its
 *   readers, and back here after Google or a password (`?signin=1`);
 * - signed in, email not verified → verify it first, and back here;
 * - a reader of this guide, or the owner → the guide, the reader in their own colours and with
 *   their own ticks — after the device check, which only a reader takes (`RecogniseDevice`);
 * - a reader removed from it → `removed`, and an account whose request was declined →
 *   `declined`: no access, and nothing to ask;
 * - any other account → `open`: a request to enroll, with an optional message; `pending` once
 *   asked; `closed` while the owner has not opened it for enrollment.
 *
 * A reader's first visit to a guide in a session also brings one motivation phrase for where they
 * stand on it (`MotivationPicker`), shown as a popup; later visits in that session bring none.
 */
class GuidePageController
{
    public function __invoke(Request $request, string $token, ArcadiaSite $site, GuidePresenter $presenter, GuidePresentation $presentation, GuideVisits $visits, RecogniseDevice $devices, GuideProgressSummary $summary, MotivationPicker $motivation): Response|RedirectResponse
    {
        $guide = Guide::query()
            ->where('link_token', $token)
            ->where('locale', $site->locale())
            ->where('status', GuideStatus::PUBLISHED)
            ->firstOrFail();

        $version = $guide->releasedVersion() ?? abort(404);

        $user = $request->user();

        if (! $user) {
            // Sign in from the presentation, and come back to the link itself.
            if ($request->boolean('signin')) {
                $request->session()->put('url.intended', $request->url());

                return redirect()->route('login');
            }

            return $this->presentation($request, $site, $guide, $presentation, $visits, 'sign_in');
        }

        // An email-and-password account reads nothing until it proves its email, then comes back.
        if (! $user->hasVerifiedEmail()) {
            return redirect()->guest(route('verification.notice'));
        }

        $reader = $guide->readers()->withTrashed()->where('user_id', $user->id)->first();
        $owner = $site->roleOf($user)?->alias === 'admin';

        if (! $owner && ($reader === null || $reader->trashed())) {
            return $this->presentation($request, $site, $guide, $presentation, $visits, $this->stateFor($request, $guide, $reader));
        }

        if ($refused = $devices->check($request)) {
            return $refused;
        }

        // The owner reading it as-is sees the guide's default; a reader, their own colours.
        $reader = $reader?->trashed() ? null : $reader;

        return Inertia::render('Guide', [
            // A quick check's answers only for the owner reading as-is: a reader's is checked here.
            ...$presenter->present($guide, $version, withAnswers: $reader === null),
            'preview' => false,
            'pageTitle' => $guide->title,
            // Shared by link, never listed: nothing here for a search engine.
            'noIndex' => true,
            'canonicalUrl' => $site->guideUrl($token),
            'privacyUrl' => $site->privacyPath(),
            'appearance' => $reader
                ? $reader->appearanceTokens()
                : Appearance::tokens($guide->default_palette, $guide->default_accent),
            'readerAppearance' => $reader ? [
                'palette' => ($reader->palette ?? Palette::ARCADIA)->value,
                'accent' => ($reader->accent ?? Accent::OWN)->value,
                'saveUrl' => route("{$site->locale()}.reader.appearance", $reader),
                'options' => Appearance::options(),
            ] : null,
            // A reader's ticks are on their account, so the server renders them. The owner reading
            // as-is ticks for the visit only: they are not a reader of it.
            'progress' => $reader ? [
                'ticked' => $reader->tickedKeys(),
                'saveUrl' => route("{$site->locale()}.reader.progress", $reader),
                // Where a quick check is answered: a right answer ticks its line there.
                'checkUrl' => route("{$site->locale()}.reader.check", $reader),
                // Only beside a reader's progress: the owner and the preview never see a stage locked.
                'unlockInOrder' => $guide->unlock_in_order,
            ] : null,
            // Their own notes, on lines and stages. None for the owner: notes are a reader's.
            'notes' => $reader ? [
                'list' => Note::query()->where('user_id', $reader->user_id)->where('guide_id', $reader->guide_id)
                    ->oldest()->get()->map(fn (Note $note) => $note->toReader()),
                'storeUrl' => route("{$site->locale()}.reader.notes.store", $reader),
                'maxLength' => Note::MAX_LENGTH,
            ] : null,
            'myGuidesUrl' => $reader ? $site->readerPath() : null,
            // What the greeting calls them, highlighted. None for the owner reading as-is.
            'readerName' => $reader?->displayName(),
            'motivation' => $reader ? $this->motivationOnce($request, $guide, $reader, $version, $summary, $motivation) : null,
            // What to cheer with when a tick completes a stage here — the guide too, on the last.
            'celebration' => $reader ? [
                'stage' => $motivation->allForGuide($guide, PhraseMoment::STAGE_DONE, $reader->displayName()),
                'done' => $motivation->allForGuide($guide, PhraseMoment::DONE, $reader->displayName()),
            ] : null,
        ])->toResponse($request);
    }

    /**
     * The phrase for where the reader stands on this guide — once per guide per session: the
     * session remembers it was given, so coming back to the guide does not pop it up again.
     */
    private function motivationOnce(Request $request, Guide $guide, Reader $reader, Version $version, GuideProgressSummary $summary, MotivationPicker $motivation): ?string
    {
        $key = "arcadia.motivated.{$guide->id}";

        if ($request->session()->has($key)) {
            return null;
        }

        $progress = $summary->for($reader, $version);
        $phrase = $motivation->forGuide($guide, PhraseMoment::forProgress($progress['done'], $progress['total']), $reader->displayName());

        if ($phrase !== null) {
            $request->session()->put($key, true);
        }

        return $phrase;
    }

    /** Where an account that may not read it stands: removed, declined, asked, or free to ask. */
    private function stateFor(Request $request, Guide $guide, ?Reader $removed): string
    {
        $asked = $guide->accessRequests()->where('user_id', $request->user()->id)->first();

        return match (true) {
            $removed !== null => 'removed',
            $asked?->status === AccessRequestStatus::DECLINED => 'declined',
            $asked !== null => 'pending',
            $guide->open_for_enrollment => 'open',
            default => 'closed',
        };
    }

    /**
     * What the guide is about, and what this visitor can do about it — never what is in it. A 200,
     * so a link's preview is fetched from it.
     */
    private function presentation(Request $request, ArcadiaSite $site, Guide $guide, GuidePresentation $presentation, GuideVisits $visits, string $state): Response
    {
        $user = $request->user();

        // A visit, for the editor's figures — not a reader taken off it, who knows the guide.
        if ($state !== 'removed') {
            $visits->record($guide, $request->userAgent(), $request->session());
        }

        return Inertia::render('GuidePresentation', [
            ...$presentation->present($guide, $site),
            'state' => $state,
            // The way in for a signed-out visitor; for an account, which one it is.
            'signInUrl' => $user ? null : $request->url().'?signin=1',
            'email' => $user?->email,
            'myGuidesUrl' => $user && $site->roleOf($user)?->alias === 'reader' ? $site->readerPath() : null,
            'accessUrl' => $state === 'open' ? route("{$site->locale()}.guide.access", $guide->link_token) : null,
            'messageMax' => AccessRequest::MAX_MESSAGE,
            'preview' => false,
        ])->toResponse($request);
    }
}
