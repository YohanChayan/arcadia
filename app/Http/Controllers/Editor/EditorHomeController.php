<?php

namespace App\Http\Controllers\Editor;

use App\Enums\GuideStatus;
use App\Enums\InquiryStage;
use App\Models\AccessRequest;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Services\ArcadiaSite;
use App\Services\GuideVisits;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the owner's sign-in lands: this language's dashboard. What needs answering first — new
 * requests, with their reply links — then where the guides stand and what a guide is offered at,
 * and what each guide's presentation brought in — visits, requests, enrollments — over the last
 * 30 days or since the start (`GuideVisits`, ADR 0022). Every figure is counted from this language's
 * own rows; nothing is estimated.
 */
class EditorHomeController
{
    public function __invoke(Request $request, ArcadiaSite $site, GuideVisits $visits): Response
    {
        // What the guides' presentations brought in: the last 30 days, or since the start.
        $window = $request->query('window') === 'all' ? 'all' : '30';

        $locale = app()->getLocale();

        $inquiries = Inquiry::query()->where('locale', $locale);
        $guides = Guide::query()->where('locale', $locale);

        return Inertia::render('editor/Home', [
            'siteUrl' => $site->url($site->homePath()),
            'stats' => [
                'inquiries' => [
                    'pending' => (clone $inquiries)->atStage(InquiryStage::PENDING)->count(),
                    'total' => (clone $inquiries)->count(),
                ],
                'guides' => [
                    'published' => (clone $guides)->where('status', GuideStatus::PUBLISHED)->count(),
                    'drafts' => (clone $guides)->where('status', GuideStatus::DRAFT)->count(),
                    'total' => (clone $guides)->count(),
                ],
                'price' => Setting::forLocale($locale)->priceLabel(),
                // Enrollment requests waiting for an answer, on this language's guides.
                'enrollments' => [
                    'pending' => AccessRequest::query()->pending()
                        ->whereHas('guide', fn ($guides) => $guides->where('locale', $locale))->count(),
                ],
            ],
            'latestInquiries' => (clone $inquiries)->with(['user:id,name', 'guide:id,inquiry_id'])->latest()->limit(5)->get()
                ->map(fn (Inquiry $inquiry) => [
                    ...$inquiry->only(['id', 'topic', 'contact', 'contact_kind', 'created_at']),
                    'stage' => $inquiry->stage()->value,
                    'name' => $inquiry->requesterName(),
                    'reply_url' => $inquiry->replyUrl(),
                ]),
            'window' => $window,
            'presentations' => $visits->forLocale($locale, $window === 'all' ? null : now()->subDays(30)->startOfDay())
                ->filter(fn (array $row) => $row['open_for_enrollment'] || $row['visits'] || $row['requests'])
                ->values(),
            'recentGuides' => (clone $guides)->latest('updated_at')->limit(5)
                ->get(['id', 'title', 'status', 'updated_at']),
        ]);
    }
}
