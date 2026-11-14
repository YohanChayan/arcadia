<?php

namespace App\Services\Site;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The errors in `laravel.log`, for Site settings → Logs. Ported from yhndevside's
 * `SystemHealthService::log()`.
 *
 * One difference: only this environment's entries count. Test runs write `testing.ERROR` into the
 * same file locally, and an error a test provoked on purpose is not one the site had.
 */
class ErrorLog
{
    /** The most entries a filtered list returns; the screen says when there are more. */
    public const MAX_ROWS = 200;

    /** An entry's first line: `[2026-10-01 12:00:00] production.ERROR: message`. */
    private const ENTRY = '/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+-]+)\] (\w+)\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/';

    public function __construct(private ?string $path = null)
    {
        $this->path ??= storage_path('logs/laravel.log');
    }

    /**
     * This environment's errors inside a window, newest first, at most `MAX_ROWS` of them.
     *
     * The file is read once, line by line, and only an entry's first line is parsed — the stack
     * trace beneath it is skipped — so a large log costs a scan, not memory. `last_day` is
     * counted in the same pass whatever the window, because it is what sets the level.
     *
     * @return array<string, mixed>
     */
    public function errors(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        // Absent, or not readable by the web server's user: either way there is nothing to show.
        $handle = is_file($this->path) ? @fopen($this->path, 'r') : false;

        if ($handle === false) {
            return ['exists' => false, 'errors' => [], 'in_range' => 0, 'last_day' => 0, 'limit' => self::MAX_ROWS, 'level' => 'ok'];
        }

        $environment = app()->environment();
        $dayAgo = now()->subDay()->getTimestamp();
        $from = $start?->getTimestamp();
        $to = $end?->getTimestamp();

        $kept = [];
        $inRange = 0;
        $lastDay = 0;

        while (($line = fgets($handle)) !== false) {
            if ($line[0] !== '[' || ! preg_match(self::ENTRY, $line, $match) || $match[2] !== $environment) {
                continue;
            }

            $at = strtotime($match[1]);

            if ($at >= $dayAgo) {
                $lastDay++;
            }

            if (($from !== null && $at < $from) || ($to !== null && $at > $to)) {
                continue;
            }

            $inRange++;
            $kept[] = ['at' => $match[1], 'level' => strtolower($match[3]), 'message' => Str::limit(rtrim($match[4]), 240)];

            // The file runs oldest to newest: keep only the newest MAX_ROWS as we go.
            if (count($kept) > self::MAX_ROWS) {
                array_shift($kept);
            }
        }
        fclose($handle);

        $size = filesize($this->path);

        return [
            'exists' => true,
            'environment' => $environment,
            'bytes' => $size,
            'errors' => array_reverse($kept),
            'in_range' => $inRange,
            'last_day' => $lastDay,
            'limit' => self::MAX_ROWS,
            'level' => match (true) {
                $lastDay > 0 => 'warn',
                $size > 50 * 1024 * 1024 => 'warn',
                default => 'ok',
            },
        ];
    }
}
