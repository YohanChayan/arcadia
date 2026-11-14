<?php

namespace App\Http\Controllers\Editor;

use App\Http\Requests\DateRangeRequest;
use App\Services\Site\SiteHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Site settings → Health: whether the machinery behind the site is running — the queue and its
 * worker, failed jobs, mail, the environment — and who is on the site right now. Site-wide: both
 * editors show the same.
 *
 * And its actions: put a failed job back on the queue, drop one or a window of them for good, and
 * prove the mail server accepts a message.
 */
class SiteHealthController
{
    public function index(DateRangeRequest $request, SiteHealth $health): Response
    {
        $failed = $request->range('failed');

        return Inertia::render('editor/settings/Health', [
            'health' => [
                'queue' => $health->queue(),
                'worker' => $health->worker(),
                'failed_jobs' => $health->failedJobs($failed['start'], $failed['end']),
                'mail' => $health->mail(),
                'environment' => $health->environment(),
                // Echoed back so the filter opens on what the URL asked for.
                'filters' => ['failed' => $request->shown('failed')],
            ],
            'visitors' => $health->visitors(),
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', __('arcadia.editor.settings.health.flash.retried'));
    }

    public function forget(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        Artisan::call('queue:forget', ['id' => $uuid]);

        return back()->with('success', __('arcadia.editor.settings.health.flash.forgotten'));
    }

    /**
     * The failed jobs inside the date filter the list is showing — "7 days" flushes those seven
     * days and leaves older failures alone. The request carries the same `failed_*` parameters as
     * the page, so what is deleted is exactly what was on screen.
     */
    public function flush(DateRangeRequest $request, SiteHealth $health): RedirectResponse
    {
        $range = $request->range('failed');

        $deleted = $health->flushFailedJobs($range['start'], $range['end']);

        return back()->with('success', __('arcadia.editor.settings.health.flash.flushed', ['count' => $deleted]));
    }

    /**
     * Sent now, not queued: this tests the mail server on its own. The queue has its own card on
     * the same screen, and a queued test that never arrives could not say which of the two failed.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $to = $request->user()->email;

        try {
            Mail::raw(__('arcadia.editor.settings.health.test_mail.body', ['time' => now()->toDateTimeString()]), function ($message) use ($to) {
                $message->to($to)->subject(__('arcadia.editor.settings.health.test_mail.subject'));
            });
        } catch (Throwable $e) {
            return back()->with('error', __('arcadia.editor.settings.health.flash.mail_failed', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('arcadia.editor.settings.health.flash.mail_sent', ['to' => $to]));
    }
}
