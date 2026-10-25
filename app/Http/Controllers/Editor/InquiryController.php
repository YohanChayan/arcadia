<?php

namespace App\Http\Controllers\Editor;

use App\Enums\InquiryPurpose;
use App\Enums\InquiryStage;
use App\Http\Requests\InquiryRequest;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What accounts asked for on this language's landing page, newest first, ten to a page, each with the
 * account's email — the one to assign the guide to — and a link that opens the answer: a WhatsApp
 * chat, or a mail on a request from before sign-in. Where each stands — nothing yet, or a guide —
 * is read from the work done on it (`InquiryStage`); deleting one removes it for good.
 *
 * Another language's request is a 404 here, whatever id is in the URL, as every editor screen is.
 */
class InquiryController
{
    public function index(Request $request): Response
    {
        // Only one stage, and one kind of contact, when asked for; anything else is every request.
        $stage = InquiryStage::tryFrom((string) $request->query('stage'));
        $kind = in_array($request->query('kind'), [Inquiry::EMAIL, Inquiry::WHATSAPP], true) ? $request->query('kind') : null;
        // Words in the topic, the contact, the typed name or the account's; the stage counts follow it.
        $search = trim((string) $request->query('search')) ?: null;
        $ofLocale = fn () => Inquiry::query()
            ->where('locale', app()->getLocale())
            ->when($kind, fn ($inquiries) => $inquiries->where('contact_kind', $kind))
            ->when($search, fn ($inquiries) => $inquiries->where(fn ($inquiries) => $inquiries
                ->whereLike('topic', "%{$search}%")
                ->orWhereLike('contact', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")
                ->orWhereHas('user', fn ($users) => $users
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%"))));

        $inquiries = $ofLocale()
            ->when($stage, fn ($inquiries) => $inquiries->atStage($stage))
            ->with(['user:id,name,email', 'guide:id,inquiry_id,title'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Inquiry $inquiry) => [
                ...$inquiry->makeHidden(['user'])->toArray(),
                'name' => $inquiry->requesterName(),
                'account_email' => $inquiry->user?->email,
                'reply_url' => $inquiry->replyUrl(),
                'stage' => $inquiry->stage()->value,
            ]);

        return Inertia::render('editor/inquiries/Index', [
            'inquiries' => $inquiries,
            'stages' => array_map(fn (InquiryStage $case) => [
                'value' => $case->value,
                'label' => __("arcadia.editor.inquiries.stages.{$case->value}"),
                'tone' => $case->tone(),
            ], InquiryStage::cases()),
            'filters' => ['stage' => $stage?->value, 'kind' => $kind, 'search' => $search],
            // Per stage, whatever the stage filter, so each choice says how many it would show.
            'stageCounts' => collect(InquiryStage::cases())
                ->mapWithKeys(fn (InquiryStage $case) => [$case->value => $ofLocale()->atStage($case)->count()]),
            'purposes' => array_map(fn (InquiryPurpose $purpose) => [
                'value' => $purpose->value,
                'label' => __('arcadia.landing.ask.purposes.'.$purpose->value),
            ], InquiryPurpose::cases()),
        ]);
    }

    /**
     * A request the owner writes themselves — for a guide they want to make, or one asked for by
     * phone — so it goes through the same "Crear guía" as any other. Mailed to nobody:
     * the owner is the one who wrote it.
     */
    public function store(InquiryRequest $request): RedirectResponse
    {
        Inquiry::create([
            'locale' => app()->getLocale(),
            ...$request->safe()->only(['topic', 'contact', 'name']),
        ]);

        return back()->with('success', __('arcadia.editor.inquiries.flash.created'));
    }

    public function destroy(int $inquiry): RedirectResponse
    {
        $this->find($inquiry)->delete();

        return back()->with('success', __('arcadia.editor.inquiries.flash.deleted'));
    }

    private function find(int $id): Inquiry
    {
        return Inquiry::query()->where('locale', app()->getLocale())->findOrFail($id);
    }
}
