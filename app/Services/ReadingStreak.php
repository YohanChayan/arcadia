<?php

namespace App\Services;

use App\Models\Tick;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A reader's run of days in a row with at least one line ticked, across every guide on their
 * account, for "My guides". Read from `guide_ticks`, so a line unticked takes its day with it.
 *
 * A run still stands while today has no tick yet: it ends only once a whole day passes without
 * one, so a reader arriving in the morning sees yesterday's count, and how to keep it.
 */
class ReadingStreak
{
    /** Readers are in Venezuela: their day ends at midnight there, not at UTC's 20:00 in Caracas. */
    public const TIMEZONE = 'America/Caracas';

    /** @return array{days: int, today: bool} */
    public function for(User $user): array
    {
        $days = Tick::query()
            ->where('user_id', $user->id)
            ->pluck('created_at')
            ->map(fn (CarbonInterface $at) => $at->setTimezone(self::TIMEZONE)->toDateString())
            ->flip();

        $day = now(self::TIMEZONE);
        $today = $days->has($day->toDateString());

        if (! $today) {
            $day = $day->subDay();
        }

        $count = 0;

        while ($days->has($day->toDateString())) {
            $count++;
            $day = $day->subDay();
        }

        return ['days' => $count, 'today' => $today];
    }
}
