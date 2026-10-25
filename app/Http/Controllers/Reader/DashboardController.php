<?php

namespace App\Http\Controllers\Reader;

use App\Enums\Accent;
use App\Enums\GuideStatus;
use App\Enums\ImageSlot;
use App\Enums\Palette;
use App\Enums\PhraseMoment;
use App\Models\Reader;
use App\Services\Appearance;
use App\Services\ArcadiaSite;
use App\Services\GuideProgressSummary;
use App\Services\MotivationPicker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A reader's own guides in this language — every guide assigned to their account, each in their
 * colours, with its link once it can be read, where they stand on it (`GuideProgressSummary`),
 * a short phrase for that moment and one to welcome them (`MotivationPicker`), and the colours to
 * change. A reader removed from every guide still gets here, to an empty list.
 */
class DashboardController
{
    public function __invoke(Request $request, ArcadiaSite $site, GuideProgressSummary $summary, MotivationPicker $motivation): Response
    {
        $readers = Reader::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('guide', fn ($guides) => $guides->where('locale', app()->getLocale()))
            ->with(['user:id,name', 'guide' => fn ($guides) => $guides
                ->with(['phrases', 'images' => fn ($images) => $images->where('slot', ImageSlot::COVER)])
                ->withExists(['versions as has_release' => fn ($versions) => $versions->whereNotNull('released_at')])])
            ->oldest()
            ->get();

        // What to call them on arriving: the name a guide has for them, else their account's.
        $name = $readers->map->displayName()->filter()->first()
            ?? (string) str($request->user()->name)->trim()->explode(' ')->first() ?: null;

        return Inertia::render('reader/Dashboard', [
            'email' => $request->user()->email,
            'welcome' => $motivation->welcome(app()->getLocale(), $name),
            'guides' => $readers->map(function (Reader $reader) use ($site, $summary, $motivation) {
                // Null until there is a release to read — nothing to count yet, nothing to cheer.
                $progress = ($version = $reader->guide->releasedVersion()) ? $summary->for($reader, $version) : null;

                return [
                    'reader' => $reader->id,
                    'progress' => $progress,
                    // A short line for where they stand on it: not started, under way, nearly, done.
                    'phrase' => $progress
                        ? $motivation->forGuide($reader->guide, PhraseMoment::forProgress($progress['done'], $progress['total']), $reader->displayName())
                        : null,
                    'title' => $reader->guide->title,
                    // Its cover's square, or null while none is uploaded.
                    'cover' => $reader->guide->cover()?->url('thumb'),
                    'level' => $reader->guide->level->label(),
                    'palette' => ($reader->palette ?? Palette::ARCADIA)->value,
                    'accent' => ($reader->accent ?? Accent::OWN)->value,
                    // Only a published guide with a release opens; until then it is being prepared.
                    'url' => $reader->guide->status === GuideStatus::PUBLISHED && $reader->guide->has_release
                        ? $site->guideUrl($reader->guide->link_token, $reader->guide->locale)
                        : null,
                ];
            })->values(),
            'appearanceOptions' => Appearance::options(),
            'pageTitle' => __('arcadia.my_guides.meta.title'),
            'noIndex' => true,
        ]);
    }
}
