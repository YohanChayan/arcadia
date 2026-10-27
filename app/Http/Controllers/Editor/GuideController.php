<?php

namespace App\Http\Controllers\Editor;

use App\Actions\SaveGuide;
use App\Actions\TellReaders;
use App\Enums\GuideStatus;
use App\Enums\ImageSlot;
use App\Enums\Level;
use App\Enums\PhraseMoment;
use App\Enums\Vocabulary;
use App\Http\Requests\GuideRequest;
use App\Models\Guide;
use App\Models\Phrase;
use App\Services\ArcadiaSite;
use App\Services\GuideVisits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guides of this site's language — listed, described, deleted. Their content (versions,
 * stages, items) is edited on its own screen, not here.
 *
 * The site's locale is `app()->getLocale()`: the editor's route group sets it from the URL, so
 * `/es/editor` only ever sees and files Spanish guides.
 */
class GuideController
{
    public function index(Request $request, ArcadiaSite $site, GuideVisits $visits): Response
    {
        // Only one status when asked for; anything else is every guide.
        $status = GuideStatus::tryFrom((string) $request->query('status'));
        // Words in the title or the category's name; the status counts follow it too.
        $search = trim((string) $request->query('search')) ?: null;
        $ofLocale = fn () => Guide::query()
            ->where('locale', app()->getLocale())
            ->when($search, fn ($guides) => $guides->where(fn ($guides) => $guides
                ->whereLike('title', "%{$search}%")
                ->orWhereRelation('category', 'name', 'like', "%{$search}%")));

        $guides = $ofLocale()
            ->when($status, fn ($guides) => $guides->where('status', $status))
            ->with(['category:id,name', 'images' => fn ($images) => $images->where('slot', ImageSlot::COVER)])
            // Every version, and how many of them were released to readers.
            ->withCount(['versions', 'readers', 'versions as released_versions_count' => fn ($versions) => $versions->whereNotNull('released_at')])
            ->withExists(['versions as has_release' => fn ($versions) => $versions->whereNotNull('released_at')])
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        // What each card's link page brought in over the last 30 days, as the editor home counts it.
        $figures = $visits->forGuides($guides->getCollection()->pluck('id'), now()->subDays(30)->startOfDay());

        $guides = $guides
            // The link to share, whatever the status, so the owner can copy or rotate it before
            // publishing — and whether a reader could open it yet (`GuideLinkDialog`).
            ->through(fn (Guide $guide) => [
                ...Arr::except($guide->toArray(), 'images'),
                'link' => $site->guideUrl($guide->link_token, $guide->locale),
                'readable' => $guide->status === GuideStatus::PUBLISHED && $guide->has_release,
                // Its cover's wide crop across the card's top, or null while none is uploaded.
                'cover' => $guide->cover()?->url('share'),
                'presentation' => $figures($guide->id),
            ]);

        return Inertia::render('editor/guides/Index', [
            'guides' => $guides,
            'levels' => $this->levelOptions(),
            'statuses' => $this->statusOptions(),
            'status' => $status?->value,
            'search' => $search,
            // Per status, whatever the filter, so each choice says how many it would show.
            'statusCounts' => $ofLocale()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('editor/guides/Create', $this->formOptions());
    }

    public function store(GuideRequest $request, SaveGuide $save): RedirectResponse
    {
        $guide = $this->save($request, $save, null);

        return redirect()
            ->route($this->name('guides.index'))
            ->with('success', __('arcadia.editor.guides.flash.created', ['title' => $guide->title]));
    }

    public function edit(Guide $guide): Response
    {
        $this->ownLocale($guide);

        return Inertia::render('editor/guides/Edit', [
            'guide' => [
                ...$guide->only('id', 'title', 'category_id'),
                'level' => $guide->level->value,
                'status' => $guide->status->value,
                'topic_ids' => $guide->topics()->pluck('guide_topics.id'),
                'goals' => $guide->goals ?? [],
                'phrase_ids' => $guide->phrases()->whereIn('moment', PhraseMoment::perGuide())->pluck('guide_phrases.id'),
                'greeting' => $guide->greeting,
                'unlock_in_order' => $guide->unlock_in_order,
                'curator_notes' => $guide->curator_notes,
            ],
            // What researched it, when it was started from a request's prompt.
            'provenance' => $guide->promptVersion ? [
                'template_id' => $guide->promptVersion->template_id,
                'template' => $guide->promptVersion->template->name,
                'number' => $guide->promptVersion->number,
                'values' => $guide->prompt_values ?? [],
                'inquiry' => $guide->inquiry?->only('id', 'topic', 'name', 'created_at'),
            ] : null,
            ...$this->formOptions(),
        ]);
    }

    public function update(GuideRequest $request, Guide $guide, SaveGuide $save, TellReaders $tell): RedirectResponse
    {
        $this->ownLocale($guide);
        $this->save($request, $save, $guide);
        // Set to published with a release already there: whoever was waiting is told now.
        $tell->waiting($guide);

        return redirect()
            ->route($this->name('guides.index'))
            ->with('success', __('arcadia.editor.guides.flash.updated', ['title' => $guide->title]));
    }

    public function destroy(Guide $guide): RedirectResponse
    {
        $this->ownLocale($guide);
        $title = $guide->title;
        // Versions and all their content cascade with it.
        $guide->delete();

        return redirect()
            ->route($this->name('guides.index'))
            ->with('success', __('arcadia.editor.guides.flash.deleted', ['title' => $title]));
    }

    /**
     * A new link for the guide; the old one stops working at once. For a link that travelled
     * further than it should.
     */
    public function rotateLink(Guide $guide): RedirectResponse
    {
        $this->ownLocale($guide);
        $guide->rotateLinkToken();

        return back()->with('success', __('arcadia.editor.guides.flash.rotated'));
    }

    private function save(GuideRequest $request, SaveGuide $save, ?Guide $guide): Guide
    {
        return $save->handle(
            $guide,
            app()->getLocale(),
            $request->validated('title'),
            Level::from($request->validated('level')),
            $request->validated('category_id'),
            GuideStatus::from($request->validated('status')),
            $request->validated('topic_ids', []),
            $request->validated('goals', []),
            $request->validated('greeting'),
            $request->validated('phrase_ids', []),
            $request->validated('unlock_in_order'),
        );
    }

    /** A guide of the other language is not this editor's, whatever the id in the URL. */
    private function ownLocale(Guide $guide): void
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
    }

    private function name(string $route): string
    {
        return app()->getLocale().'.editor.'.$route;
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        $terms = fn (Vocabulary $vocabulary) => $vocabulary->model()::query()
            ->where('locale', app()->getLocale())
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'levels' => $this->levelOptions(),
            'statuses' => $this->statusOptions(),
            'categories' => $terms(Vocabulary::CATEGORIES),
            'topics' => $terms(Vocabulary::TOPICS),
            // To choose from, by moment — only those a guide chooses for (`PhraseMoment::perGuide()`).
            'phrases' => Phrase::query()->where('locale', app()->getLocale())->whereIn('moment', PhraseMoment::perGuide())
                ->oldest('id')->get(['id', 'moment', 'text']),
            'moments' => PhraseMoment::guideOptions(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function levelOptions(): array
    {
        return array_map(fn (Level $level) => ['value' => $level->value, 'label' => $level->label()], Level::cases());
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    private function statusOptions(): array
    {
        return array_map(fn (GuideStatus $status) => [
            'value' => $status->value,
            'label' => __("arcadia.editor.guides.statuses.{$status->value}"),
            'tone' => $status === GuideStatus::PUBLISHED ? 'ok' : 'neutral',
        ], GuideStatus::cases());
    }
}
