<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Guide;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a guide's presentation brings in (ADR 0022), counted on the server from Arcadia's own rows:
 *
 * - **visits** — the presentation shown, once per browser session per guide per day, into a
 *   counter per guide and day (`guide_visits`). Never the owner or the guide's readers, who are
 *   shown the guide, nor a link-preview fetcher or crawler. Nothing about the visitor is kept;
 * - **requests** — enrollment requests made;
 * - **enrollments** — requests approved. A reader the owner adds by hand is not counted: no visit
 *   brought them.
 */
class GuideVisits
{
    /** Link-preview fetchers and crawlers, by user agent: they open the page, nobody looks at it. */
    private const ROBOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|facebookcatalog|whatsapp|telegram|discord|skype|embedly|curl|wget|python|headless|lighthouse/i';

    /** One visit by this browser's session, unless it is a robot or already counted today. */
    public function record(Guide $guide, ?string $userAgent, Session $session): void
    {
        if (preg_match(self::ROBOTS, (string) $userAgent) === 1) {
            return;
        }

        $today = now()->toDateString();
        $key = "arcadia.visited.{$guide->id}";

        if ($session->get($key) === $today) {
            return;
        }

        $session->put($key, $today);

        DB::table('guide_visits')->upsert(
            [['guide_id' => $guide->id, 'day' => $today, 'visits' => 1]],
            ['guide_id', 'day'],
            ['visits' => DB::raw('visits + 1')],
        );
    }

    /**
     * Each of the language's guides with what its presentation brought in since then — or ever.
     *
     * @return Collection<int, array{id: int, title: string, open_for_enrollment: bool, visits: int, requests: int, enrollments: int}>
     */
    public function forLocale(string $locale, ?CarbonInterface $since): Collection
    {
        $guides = Guide::query()->where('locale', $locale)->orderBy('title')->get(['id', 'title', 'open_for_enrollment']);
        $figures = $this->figures($guides->pluck('id'), $since);

        return $guides->map(fn (Guide $guide) => [
            'id' => $guide->id,
            'title' => $guide->title,
            'open_for_enrollment' => $guide->open_for_enrollment,
            ...$figures($guide->id),
        ]);
    }

    /**
     * These guides' figures since then — or ever — read back per guide: one query per count, for
     * a page of guides at once.
     *
     * @param  Collection<int, int>  $ids
     * @return \Closure(int): array{visits: int, requests: int, enrollments: int}
     */
    public function forGuides(Collection $ids, ?CarbonInterface $since): \Closure
    {
        return $this->figures($ids, $since);
    }

    /**
     * Three grouped counts for these guides, read back per guide.
     *
     * @param  Collection<int, int>  $ids
     * @return \Closure(int): array{visits: int, requests: int, enrollments: int}
     */
    private function figures(Collection $ids, ?CarbonInterface $since): \Closure
    {
        $visits = DB::table('guide_visits')->whereIn('guide_id', $ids)
            ->when($since, fn ($query) => $query->where('day', '>=', $since->toDateString()))
            ->groupBy('guide_id')->selectRaw('guide_id, SUM(visits) as total')->pluck('total', 'guide_id');

        $requests = AccessRequest::query()->whereIn('guide_id', $ids)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->groupBy('guide_id')->selectRaw('guide_id, COUNT(*) as total')->pluck('total', 'guide_id');

        $enrollments = AccessRequest::query()->whereIn('guide_id', $ids)
            ->where('status', AccessRequestStatus::APPROVED)
            ->when($since, fn ($query) => $query->where('decided_at', '>=', $since))
            ->groupBy('guide_id')->selectRaw('guide_id, COUNT(*) as total')->pluck('total', 'guide_id');

        return fn (int $id) => [
            'visits' => (int) ($visits[$id] ?? 0),
            'requests' => (int) ($requests[$id] ?? 0),
            'enrollments' => (int) ($enrollments[$id] ?? 0),
        ];
    }
}
