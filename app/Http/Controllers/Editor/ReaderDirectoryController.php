<?php

namespace App\Http\Controllers\Editor;

use App\Enums\GuideStatus;
use App\Models\Reader;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everyone this language's guides are for, one row per email however many guides they were
 * given, twenty to a page, searchable by email or name, and filtered by access: those with an
 * account, or those still pending — given a guide, invited or not, but never signed in, so
 * never opened it (signing in links every assignment of theirs, `LinkReaderAssignments`).
 * Each row carries its guides, for the dialog that lists them. A removed reader no longer counts.
 *
 * Read-only: readers are added and removed on each guide's own readers screen.
 */
class ReaderDirectoryController
{
    public const WITH_ACCOUNT = 'account';

    public const PENDING = 'pending';

    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->query('search')) ?: null;
        $access = in_array($request->query('access'), [self::WITH_ACCOUNT, self::PENDING], true) ? $request->query('access') : null;

        $ofLocale = fn (): Builder => Reader::query()
            ->whereHas('guide', fn (Builder $guides) => $guides->where('locale', app()->getLocale()));

        $readers = $ofLocale()
            ->when($search, fn (Builder $readers) => $readers->where(fn (Builder $readers) => $readers
                ->whereLike('email', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")
                ->orWhereHas('user', fn (Builder $users) => $users->whereLike('name', "%{$search}%"))))
            ->toBase()
            ->select('email')
            ->selectRaw('count(*) as guides_count, max(user_id) as user_id, max(name) as name, max(created_at) as last_assigned_at')
            ->groupBy('email')
            ->when($access === self::WITH_ACCOUNT, fn ($readers) => $readers->havingRaw('max(user_id) is not null'))
            ->when($access === self::PENDING, fn ($readers) => $readers->havingRaw('max(user_id) is null'))
            ->orderByDesc('last_assigned_at')
            ->orderBy('email')
            ->paginate(20)
            ->withQueryString();

        $emails = $readers->pluck('email');
        $guidesOf = $ofLocale()->whereIn('email', $emails)->with('guide:id,title,status')->oldest('id')->get()->groupBy('email');
        $accountNames = User::query()->whereIn('id', $readers->pluck('user_id')->filter())->pluck('name', 'id');

        $readerCount = $ofLocale()->distinct()->count('email');
        $withAccount = $ofLocale()->whereNotNull('user_id')->distinct()->count('email');

        return Inertia::render('editor/readers/Index', [
            'readers' => $readers->through(fn (object $row) => [
                'email' => $row->email,
                'name' => $row->name ?: ($accountNames[$row->user_id] ?? null),
                'has_account' => $row->user_id !== null,
                'guides_count' => (int) $row->guides_count,
                'last_assigned_at' => $row->last_assigned_at,
                'guides' => ($guidesOf[$row->email] ?? collect())->map(fn (Reader $reader) => [
                    'id' => $reader->guide->id,
                    'title' => $reader->guide->title,
                    'status' => $reader->guide->status->value,
                    'assigned_at' => $reader->created_at,
                    // The invitation email: when it went, or null — not asked for, or waiting for the guide to open.
                    'notified_at' => $reader->notified_at,
                ])->values(),
            ]),
            // Whatever the search and the filter: how many people, and how many have signed in at least once.
            'totals' => [
                'readers' => $readerCount,
                'with_account' => $withAccount,
                'pending' => $readerCount - $withAccount,
            ],
            'statuses' => GuideStatus::options(),
            'filters' => ['search' => $search, 'access' => $access],
        ]);
    }
}
