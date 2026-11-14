<?php

namespace App\Services\Site;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * The things that fail silently: a queue worker that stopped, a mail job that failed, a vector
 * store that went away, debug left on in production.
 *
 * None of them shows an error to anyone. Guide emails simply stop arriving and the assistant
 * stops finding passages. This reads each one from its own source — the `jobs` and `failed_jobs`
 * tables, the worker's heartbeat, the config, the `vectors` connection — and gives it a level:
 * `ok`, `warn` or `error`.
 *
 * Ported from yhndevside's `SystemHealthService`, less its scheduler, calendar and social cards:
 * Arcadia runs no scheduler, so the worker's own heartbeat is what tells a quiet queue from a
 * dead one.
 */
class SiteHealth
{
    /** Written by the queue worker while it loops — see `AppServiceProvider`. */
    public const WORKER_HEARTBEAT = 'health:worker';

    /** How often the worker writes it: the cache is the database, so not every loop. */
    public const WORKER_HEARTBEAT_SECONDS = 30;

    /** A worker loops every few seconds even when idle; this long without a beat, it is gone. */
    private const WORKER_STALE_SECONDS = 180;

    /** The most rows a filtered list returns; the screen says when there are more. */
    public const MAX_ROWS = 200;

    /**
     * @return array<string, mixed>
     */
    public function queue(): array
    {
        $driver = config('queue.default');

        if ($driver !== 'database') {
            return ['driver' => $driver, 'measurable' => false, 'level' => 'ok'];
        }

        $waiting = DB::table('jobs')->whereNull('reserved_at');
        $oldest = (clone $waiting)->min('available_at');
        $age = $oldest ? max(0, now()->getTimestamp() - (int) $oldest) : 0;

        return [
            'driver' => $driver,
            'measurable' => true,
            'waiting' => $waiting->count(),
            'running' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'oldest_seconds' => $oldest ? $age : null,
            // A job waiting this long means nothing is taking jobs off the queue.
            'level' => match (true) {
                $age > 600 => 'error',
                $age > 120 => 'warn',
                default => 'ok',
            },
        ];
    }

    /**
     * When the worker last looped. Never seen is a warning, not an error: a fresh cache, or a
     * machine with no worker on purpose, looks the same.
     *
     * @return array<string, mixed>
     */
    public function worker(): array
    {
        $lastSeen = Cache::get(self::WORKER_HEARTBEAT);
        $seconds = $lastSeen ? (int) now()->diffInSeconds(CarbonImmutable::parse($lastSeen), true) : null;

        return [
            'last_seen' => $lastSeen,
            'seconds_ago' => $seconds,
            'level' => match (true) {
                $seconds === null => 'warn',
                $seconds > self::WORKER_STALE_SECONDS => 'error',
                default => 'ok',
            },
        ];
    }

    /** Called from the worker's loop, at most once per `WORKER_HEARTBEAT_SECONDS`. */
    public function recordWorkerHeartbeat(): void
    {
        Cache::forever(self::WORKER_HEARTBEAT, now()->toAtomString());
    }

    /**
     * The failed-jobs table, filtered to a window. `count` is every failed job regardless of the
     * window — any failure is an error until it is retried or deleted — and `in_range` is how many
     * fall inside it. At most `MAX_ROWS` are listed, newest first.
     *
     * @return array<string, mixed>
     */
    public function failedJobs(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $count = DB::table('failed_jobs')->count();

        $inWindow = $this->failedJobsWithin($start, $end);

        $jobs = (clone $inWindow)->latest('failed_at')->limit(self::MAX_ROWS)->get()
            ->map(fn (stdClass $job) => [
                'uuid' => $job->uuid,
                'job' => class_basename(data_get(json_decode($job->payload, true), 'displayName', 'Unknown')),
                'queue' => $job->queue,
                'failed_at' => CarbonImmutable::parse($job->failed_at)->toAtomString(),
                'error' => Str::limit(Str::before($job->exception, "\n"), 220),
            ])->all();

        return [
            'count' => $count,
            'in_range' => $inWindow->count(),
            'jobs' => $jobs,
            'limit' => self::MAX_ROWS,
            'level' => $count ? 'error' : 'ok',
        ];
    }

    /** Delete the failed jobs inside a window — the same window the list is showing. */
    public function flushFailedJobs(?CarbonInterface $start = null, ?CarbonInterface $end = null): int
    {
        return $this->failedJobsWithin($start, $end)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function mail(): array
    {
        $mailer = config('mail.default');
        $host = config("mail.mailers.{$mailer}.host");

        return [
            'mailer' => $mailer,
            'host' => $host,
            'port' => config("mail.mailers.{$mailer}.port"),
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            // `log` and `array` accept everything and deliver nothing.
            'level' => match (true) {
                in_array($mailer, ['log', 'array'], true) => app()->isProduction() ? 'error' : 'warn',
                $mailer === 'smtp' && blank($host) => 'error',
                blank(config('mail.from.address')) => 'warn',
                default => 'ok',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function environment(): array
    {
        $production = app()->isProduction();
        $debug = (bool) config('app.debug');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $ssrEnabled = (bool) config('inertia.ssr.enabled');
        $ssrReachable = $ssrEnabled ? $this->reachable((string) config('inertia.ssr.url')) : null;
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        $checks = [
            // Stack traces, queries and environment values shown to any visitor who hits an error.
            'debug' => $debug && $production ? 'error' : 'ok',
            'https' => ! $https && $production ? 'warn' : 'ok',
            'ssr' => $ssrEnabled && ! $ssrReachable ? 'warn' : 'ok',
            'storage_link' => is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'ok' : 'warn',
            'disk' => $free !== false && $total ? ($free / $total < 0.05 ? 'error' : ($free / $total < 0.15 ? 'warn' : 'ok')) : 'ok',
            // The knowledge index: without it the assistant answers with no passages.
            'vectors' => $this->vectorsReachable() ? 'ok' : 'error',
        ];

        return [
            'env' => app()->environment(),
            'debug' => $debug,
            'url' => config('app.url'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'drivers' => [
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
                'cache' => config('cache.default'),
                'mail' => config('mail.default'),
            ],
            'ssr' => ['enabled' => $ssrEnabled, 'url' => config('inertia.ssr.url'), 'reachable' => $ssrReachable],
            'disk' => ['free' => $free ?: null, 'total' => $total ?: null],
            'checks' => $checks,
            'level' => $this->worst($checks),
        ];
    }

    /**
     * Who is on the site right now, from the database session store. Sessions expire after
     * `session.lifetime` minutes, so this is a live figure only — it has no history.
     *
     * @return array<string, mixed>
     */
    public function visitors(): array
    {
        if (config('session.driver') !== 'database') {
            return ['measurable' => false, 'driver' => config('session.driver')];
        }

        $hour = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(60)->getTimestamp())
            ->get(['user_id', 'user_agent', 'last_activity']);

        $bot = fn (stdClass $session) => (bool) preg_match('/bot|crawl|spider|slurp|curl|wget|python|headless|lighthouse|preview/i', (string) $session->user_agent);
        $within = fn (int $minutes) => $hour->where('last_activity', '>=', now()->subMinutes($minutes)->getTimestamp());
        $sessions = $within(30);

        return [
            'measurable' => true,
            'last_5' => $within(5)->reject($bot)->count(),
            'last_30' => $sessions->reject($bot)->count(),
            'last_60' => $hour->reject($bot)->count(),
            'signed_in' => $sessions->whereNotNull('user_id')->count(),
            'guests' => $sessions->whereNull('user_id')->reject($bot)->count(),
            'bots' => $sessions->filter($bot)->count(),
        ];
    }

    /**
     * @param  array<array-key, string>  $levels
     */
    public function worst(array $levels): string
    {
        return match (true) {
            in_array('error', $levels, true) => 'error',
            in_array('warn', $levels, true) => 'warn',
            default => 'ok',
        };
    }

    private function failedJobsWithin(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return DB::table('failed_jobs')
            ->when($start, fn ($query) => $query->where('failed_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('failed_at', '<=', $end));
    }

    private function vectorsReachable(): bool
    {
        try {
            DB::connection('vectors')->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function reachable(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 80;

        // A URL with no host, as a misconfigured `INERTIA_SSR_URL` would give, reaches nothing.
        if (! is_string($host) || ! is_int($port)) {
            return false;
        }

        $socket = @fsockopen($host, $port, $errno, $error, 0.5);

        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }
}
