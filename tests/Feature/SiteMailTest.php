<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Jobs\SendAccessRequestNotificationJob;
use App\Jobs\SendInquiryNotificationJob;
use App\Listeners\RecordSentMail;
use App\Mail\AccessRequestedMail;
use App\Mail\GuideSharedMail;
use App\Mail\InquiryReceivedMail;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\SentMail;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Site settings → Mail: every email the site sends is recorded as it leaves — when, to whom, its
 * subject and which mail — never its body, kept 30 days, and listed newest first in a window.
 *
 * Mail goes through the real `array` mailer here, not `Mail::fake()`: the record is written on
 * the framework's `MessageSent`, which a fake never fires.
 */
class SiteMailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        config(['mail.business_address' => 'owner@arcadia.test']);

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'email' => 'owner@arcadia.test',
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
    }

    private function guide(): Guide
    {
        $guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1]);
        $guide->latestVersion()->release();
        $guide->update(['status' => GuideStatus::PUBLISHED]);

        return $guide;
    }

    public function test_each_of_the_five_senders_is_recorded_without_its_body(): void
    {
        $guide = $this->guide();
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']);

        // A reader added to a guide (the queue is sync in tests: the job runs at once).
        $this->actingAs($this->owner)->post("/es/editor/guides/{$guide->id}/readers", ['email' => 'ana@gmail.com', 'notify' => true]);
        SendInquiryNotificationJob::dispatch(Inquiry::create(['locale' => 'es', 'topic' => 'Canva', 'contact' => 'eva@gmail.com']));
        SendAccessRequestNotificationJob::dispatch($guide->accessRequests()->create(['user_id' => $reader->id]));
        $reader->notify(new QueuedVerifyEmail);
        $reader->notify(new QueuedResetPassword('a-token'));

        $this->assertEqualsCanonicalizing([
            [GuideSharedMail::class, 'ana@gmail.com'],
            [InquiryReceivedMail::class, 'owner@arcadia.test'],
            [AccessRequestedMail::class, 'owner@arcadia.test'],
            [QueuedVerifyEmail::class, 'bob@gmail.com'],
            [QueuedResetPassword::class, 'bob@gmail.com'],
        ], SentMail::all()->map(fn (SentMail $mail) => [$mail->mailable, $mail->recipient])->all());

        $this->assertTrue(SentMail::all()->every(fn (SentMail $mail) => filled($mail->subject) && $mail->sent_at->toDateTimeString() === now()->toDateTimeString()));
        $this->assertStringContainsString('Excel para el trabajo', SentMail::where('mailable', GuideSharedMail::class)->sole()->subject);
        $this->assertSame('es', SentMail::where('mailable', GuideSharedMail::class)->sole()->locale);

        $response = $this->get('/en/editor/settings/mail')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('editor/settings/Mail')
                ->where('filter.range', '24h')
                ->where('sent.in_range', 5)
                ->has('sent.mails', 5)
                ->where('sent.retention_days', 30)
                ->has('sent.mails.0', fn ($mail) => $mail->hasAll(['id', 'sent_at', 'recipient', 'subject', 'kind', 'locale'])));

        // The guide's link is in the body only; no body, so no link.
        $this->assertStringNotContainsString($guide->link_token, $response->getContent());
        $this->assertContains('Guide shared', collect($response->inertiaProps('sent.mails'))->pluck('kind')->all());
    }

    public function test_the_list_is_newest_first_in_the_window_and_a_raw_send_is_direct(): void
    {
        $this->travelTo(now()->subDays(3));
        Mail::raw('old', fn ($message) => $message->to('old@gmail.com')->subject('Three days ago'));
        $this->travelBack();
        $this->freezeTime();
        Mail::raw('new', fn ($message) => $message->to('new@gmail.com')->subject('Just now'));

        $this->actingAs($this->owner)->get('/es/editor/settings/mail')
            ->assertInertia(fn ($page) => $page
                ->where('sent.in_range', 1)
                ->where('sent.mails.0.subject', 'Just now')
                ->where('sent.mails.0.kind', __('arcadia.editor.settings.mail.kinds.direct')));

        $this->get('/es/editor/settings/mail?mail_range=7d')
            ->assertInertia(fn ($page) => $page
                ->where('sent.in_range', 2)
                ->where('sent.mails.0.subject', 'Just now')
                ->where('sent.mails.1.subject', 'Three days ago'));

        $this->get('/es/editor/settings/mail?mail_range=forever')->assertSessionHasErrors('mail_range');
    }

    public function test_a_mail_older_than_thirty_days_is_pruned_when_the_next_is_sent(): void
    {
        SentMail::create(['sent_at' => now()->subDays(RecordSentMail::RETENTION_DAYS + 1), 'recipient' => 'gone@gmail.com', 'subject' => 'Old']);
        SentMail::create(['sent_at' => now()->subDays(RecordSentMail::RETENTION_DAYS - 1), 'recipient' => 'kept@gmail.com', 'subject' => 'Kept']);

        Mail::raw('body', fn ($message) => $message->to('new@gmail.com')->subject('New'));

        $this->assertEqualsCanonicalizing(['kept@gmail.com', 'new@gmail.com'], SentMail::pluck('recipient')->all());
    }
}
