<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Site settings → Health: the queue, its worker, failed jobs and what can be done with them, mail
 * and the environment, read site-wide in either editor.
 */
class SiteHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
    }

    private function failedJob(string $failedAt): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'arcadia',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\SendGuideSharedJob', 'attempts' => 3]),
            'exception' => "Connection refused\n#0 stack",
            'failed_at' => $failedAt,
        ]);

        return $uuid;
    }

    public function test_the_tab_reads_every_source_in_either_editor(): void
    {
        foreach (['es', 'en'] as $locale) {
            $this->actingAs($this->owner)->get("/{$locale}/editor/settings/health")
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('editor/settings/Health')
                    ->has('health.queue.level')
                    ->has('health.worker.level')
                    ->has('health.failed_jobs.level')
                    ->has('health.mail.level')
                    ->has('health.environment.checks.vectors')
                    ->where('visitors.measurable', config('session.driver') === 'database'));
        }
    }

    public function test_the_worker_is_unseen_until_it_loops_and_goes_stale_when_it_stops(): void
    {
        $level = fn () => $this->actingAs($this->owner)->get('/es/editor/settings/health')->inertiaProps('health.worker.level');

        $this->assertSame('warn', $level());

        event(new Looping('database', 'arcadia,knowledge'));
        $this->assertSame('ok', $level());

        $this->travel(4)->minutes();
        $this->assertSame('error', $level());
    }

    public function test_failed_jobs_default_to_the_last_day_and_widen_with_the_filter(): void
    {
        $this->failedJob(now()->subHours(2));
        $this->failedJob(now()->subDays(3));
        $this->failedJob(now()->subDays(40));

        $this->actingAs($this->owner)->get('/es/editor/settings/health')
            ->assertInertia(fn ($page) => $page
                ->where('health.failed_jobs.level', 'error')
                ->where('health.failed_jobs.count', 3)
                ->where('health.failed_jobs.in_range', 1)
                ->where('health.failed_jobs.jobs.0.job', 'SendGuideSharedJob')
                ->where('health.failed_jobs.jobs.0.error', 'Connection refused')
                ->where('health.filters.failed.range', '24h'));

        $this->get('/es/editor/settings/health?failed_range=7d')
            ->assertInertia(fn ($page) => $page->where('health.failed_jobs.in_range', 2));

        $day = now()->subDays(3)->toDateString();
        $this->get("/es/editor/settings/health?failed_range=custom&failed_from={$day}&failed_to={$day}")
            ->assertInertia(fn ($page) => $page->where('health.failed_jobs.in_range', 1)->where('health.filters.failed.from', $day));
    }

    public function test_a_custom_range_needs_both_days_in_order_and_an_unknown_range_is_refused(): void
    {
        $this->actingAs($this->owner);

        $this->get('/es/editor/settings/health?failed_range=forever')->assertSessionHasErrors('failed_range');
        $this->get('/es/editor/settings/health?failed_range=custom&failed_from=2026-11-10')->assertSessionHasErrors('failed_to');
        $this->get('/es/editor/settings/health?failed_range=custom&failed_from=2026-11-10&failed_to=2026-11-01')->assertSessionHasErrors('failed_to');
    }

    public function test_a_retried_job_goes_back_on_the_queue(): void
    {
        $uuid = $this->failedJob(now());

        $this->actingAs($this->owner)->post("/es/editor/settings/health/failed-jobs/{$uuid}/retry")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'arcadia')->count());
    }

    public function test_a_failed_job_is_deleted_and_an_unknown_one_is_not_found(): void
    {
        $uuid = $this->failedJob(now());

        $this->actingAs($this->owner)->delete("/es/editor/settings/health/failed-jobs/{$uuid}")->assertSessionHas('success');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        $this->delete("/es/editor/settings/health/failed-jobs/{$uuid}")->assertNotFound();
        $this->post("/es/editor/settings/health/failed-jobs/{$uuid}/retry")->assertNotFound();
    }

    public function test_flush_deletes_only_the_filtered_window(): void
    {
        $this->failedJob(now()->subHour());
        $this->failedJob(now()->subDays(3));
        $this->failedJob(now()->subDays(90));

        $this->actingAs($this->owner)->delete('/es/editor/settings/health/failed-jobs')->assertSessionHas('success');
        $this->assertSame(2, DB::table('failed_jobs')->count());

        $this->delete('/es/editor/settings/health/failed-jobs', ['failed_range' => '7d']);
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'The 90-day-old failure is outside "7 days" and stays.');
    }

    public function test_the_test_email_goes_to_the_signed_in_owner(): void
    {
        Event::fake([MessageSent::class]);

        $this->actingAs($this->owner)->post('/es/editor/settings/health/test-mail')
            ->assertSessionHas('success');

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->getTo()[0]->getAddress() === $this->owner->email);
    }
}
