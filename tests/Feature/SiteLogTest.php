<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Site\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Site settings → Logs: this environment's errors from `laravel.log`, the first line of each,
 * newest first, in a date window.
 */
class SiteLogTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->path = tempnam(sys_get_temp_dir(), 'laravel-log');
        $this->app->instance(ErrorLog::class, new ErrorLog($this->path));

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * @param  list<array{string, string, string, string}>  $entries  [when, environment, level, message]
     */
    private function writeLog(array $entries): void
    {
        file_put_contents($this->path, collect($entries)->map(fn (array $entry) => sprintf(
            "[%s] %s.%s: %s\n#0 /var/www/app/Some.php(12): trace line\n",
            $entry[0], $entry[1], $entry[2], $entry[3],
        ))->implode(''));
    }

    private function at(string $relative): string
    {
        return now()->modify($relative)->format('Y-m-d H:i:s');
    }

    public function test_only_this_environments_errors_show_newest_first_without_their_trace(): void
    {
        $env = app()->environment();

        $this->writeLog([
            [$this->at('-3 hours'), $env, 'ERROR', 'Older failure'],
            [$this->at('-2 hours'), $env, 'INFO', 'Not an error'],
            [$this->at('-2 hours'), 'production', 'ERROR', 'Another environment'],
            [$this->at('-1 hour'), $env, 'CRITICAL', 'Newest failure'],
        ]);

        $this->get('/es/editor/settings/logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('editor/settings/Logs')
                ->where('log.level', 'warn')
                ->where('log.in_range', 2)
                ->where('log.last_day', 2)
                ->where('log.errors.0.message', 'Newest failure')
                ->where('log.errors.0.level', 'critical')
                ->where('log.errors.1.message', 'Older failure')
                ->where('filter.range', '24h'));
    }

    public function test_the_window_defaults_to_the_last_day_and_widens_with_the_filter(): void
    {
        $env = app()->environment();

        $this->writeLog([
            [$this->at('-10 days'), $env, 'ERROR', 'Ten days ago'],
            [$this->at('-3 days'), $env, 'ERROR', 'Three days ago'],
        ]);

        $this->get('/es/editor/settings/logs')
            ->assertInertia(fn ($page) => $page->where('log.in_range', 0)->where('log.level', 'ok'));

        $this->get('/es/editor/settings/logs?log_range=7d')
            ->assertInertia(fn ($page) => $page->where('log.in_range', 1)->where('log.errors.0.message', 'Three days ago'));

        $day = now()->subDays(10)->toDateString();
        $this->get("/es/editor/settings/logs?log_range=custom&log_from={$day}&log_to={$day}")
            ->assertInertia(fn ($page) => $page->where('log.in_range', 1)->where('filter.from', $day));

        $this->get('/es/editor/settings/logs?log_range=forever')->assertSessionHasErrors('log_range');
    }

    public function test_a_missing_file_is_said_and_not_an_error(): void
    {
        unlink($this->path);

        $this->get('/es/editor/settings/logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('log.exists', false)->where('log.level', 'ok'));
    }
}
