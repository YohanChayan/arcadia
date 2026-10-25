<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Jobs\SendInquiryNotificationJob;
use App\Mail\InquiryReceivedMail;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Asking for a guide: anyone — no account needed — posts from the landing page's popup to its
 * host, the request is stored in that host's language with the contact typed (and the account,
 * when signed in with a verified email) and mailed to the owner; the owner reads, answers and
 * clears it in the editor — each host its own.
 */
class InquiryTest extends TestCase
{
    use RefreshDatabase;

    /** An Arcadia account, signed in: a reader, its email verified unless said otherwise. */
    private function signInAsReader(array $attributes = []): User
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create([
            'name' => 'María',
            'email' => 'maria@gmail.com',
            ...$attributes,
        ]);
        $this->actingAs($reader);

        return $reader;
    }

    private function ask(array $fields = [], string $path = '/pedir')
    {
        // Sent from the landing page in the request's own language.
        return $this->from($path === '/ask' ? '/en' : '/es')->post($path, [
            'topic' => 'Excel para mi entrevista',
            'contact' => '0414-123 4567',
            ...$fields,
        ]);
    }

    private function signInAsOwner(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    public function test_a_request_is_stored_in_the_hosts_language_and_mailed_to_the_owner(): void
    {
        Mail::fake();

        $this->ask()->assertRedirect('/es')->assertSessionHasNoErrors();

        $inquiry = Inquiry::sole();
        $this->assertSame('es', $inquiry->locale);
        $this->assertSame('Excel para mi entrevista', $inquiry->topic);
        $this->assertNull($inquiry->user_id);
        $this->assertSame('0414-123 4567', $inquiry->contact);
        $this->assertSame(Inquiry::WHATSAPP, $inquiry->contact_kind);

        Mail::assertSent(InquiryReceivedMail::class, fn ($mail) => $mail->hasTo(config('mail.business_address'))
            && $mail->inquiry->is($inquiry));

        $this->ask(['contact' => 'ana@example.com'], '/ask')->assertSessionHasNoErrors();
        $this->assertSame('en', Inquiry::latest('id')->first()->locale);
    }

    public function test_only_the_topic_and_a_contact_are_asked_for(): void
    {
        Queue::fake();

        // "What for" is no longer asked; one sent anyway is not kept.
        $this->ask(['purpose' => 'job'])->assertSessionHasNoErrors();
        $this->assertNull(Inquiry::sole()->purpose);

        Queue::assertPushed(SendInquiryNotificationJob::class);
    }

    public function test_the_contact_is_a_whatsapp_number_or_an_email(): void
    {
        Queue::fake();
        // More attempts than the limit allows; the limit has its own test.
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach (['', 'mañana', '123', 'ana@', '0414 123 4567 ext'] as $contact) {
            $this->ask(['contact' => $contact])->assertSessionHasErrors('contact');
        }

        $this->ask(['topic' => ' '])->assertSessionHasErrors('topic');

        $this->assertSame(0, Inquiry::count());
        Queue::assertNothingPushed();
    }

    public function test_a_bot_filling_the_hidden_field_is_refused(): void
    {
        Queue::fake();

        $this->ask(['website' => 'https://spam.example'])->assertSessionHasErrors('website');

        $this->assertSame(0, Inquiry::count());
    }

    public function test_answering_opens_the_right_channel(): void
    {
        Queue::fake();

        $replies = [
            '0414-123 4567' => 'https://wa.me/584141234567',
            '+58 414 123 4567' => 'https://wa.me/584141234567',
            '0058 414 123 4567' => 'https://wa.me/584141234567',
            '+1 (555) 123-4567' => 'https://wa.me/15551234567',
            'ana@example.com' => 'mailto:ana@example.com',
        ];

        foreach ($replies as $contact => $url) {
            $this->assertSame($url, Inquiry::create([
                'locale' => 'es', 'topic' => 'Excel', 'contact' => $contact,
            ])->replyUrl());
        }
    }

    public function test_a_mail_outage_does_not_lose_the_request(): void
    {
        config(['queue.default' => 'sync']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('550 too many emails per second'));

        $this->ask()->assertRedirect('/es');

        $this->assertSame(1, Inquiry::count());
    }

    public function test_one_address_cannot_flood_the_owner(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->ask()->assertRedirect();
        }

        $this->ask()->assertStatus(429);
        $this->assertSame(5, Inquiry::count());
    }

    public function test_a_request_takes_the_language_of_the_path_it_was_sent_to(): void
    {
        Queue::fake();

        $this->ask(['topic' => 'Excel'], '/pedir')->assertRedirect('/es');
        $this->ask(['topic' => 'Excel'], '/ask')->assertRedirect('/en');

        $this->assertSame(['es', 'en'], Inquiry::query()->orderBy('id')->pluck('locale')->all());
    }

    public function test_the_editor_lists_this_hosts_requests_newest_first(): void
    {
        Queue::fake();
        $this->signInAsOwner();

        Inquiry::create(['locale' => 'es', 'topic' => 'Inglés', 'contact' => 'ana@example.com']);
        Inquiry::create(['locale' => 'es', 'topic' => 'Excel', 'contact' => '0414 123 4567'])->forceFill(['created_at' => now()->addMinute()])->save();
        Inquiry::create(['locale' => 'en', 'topic' => 'English', 'contact' => 'bob@example.com']);

        $this->get('/es/editor/requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/inquiries/Index')
                ->where('inquiries.total', 2)
                ->where('inquiries.data.0.topic', 'Excel')
                ->where('inquiries.data.0.reply_url', 'https://wa.me/584141234567')
                ->where('inquiries.data.0.stage', 'pending')
                ->where('stageCounts', ['pending' => 2, 'guide' => 0]));
    }

    public function test_the_editor_filters_requests_by_stage_contact_and_words(): void
    {
        Queue::fake();
        $this->signInAsOwner();

        $excel = Inquiry::create(['locale' => 'es', 'topic' => 'Excel', 'contact' => '0414 123 4567', 'name' => 'Ana']);
        Inquiry::create(['locale' => 'es', 'topic' => 'Canva', 'contact' => 'luis@example.com']);
        Inquiry::create(['locale' => 'es', 'topic' => 'Excel avanzado', 'contact' => 'eva@example.com']);
        Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC])->forceFill(['inquiry_id' => $excel->id])->save();

        $topics = fn (array $query) => collect($this->get('/es/editor/requests?'.http_build_query($query))
            ->assertOk()->viewData('page')['props']['inquiries']['data'])->pluck('topic')->sort()->values()->all();

        $this->assertSame(['Excel'], $topics(['stage' => 'guide']));
        $this->assertSame(['Canva', 'Excel avanzado'], $topics(['stage' => 'pending']));
        $this->assertSame(['Excel'], $topics(['kind' => 'whatsapp']));
        $this->assertSame(['Excel', 'Excel avanzado'], $topics(['search' => 'excel']));
        // The name typed, and the contact, are searched too.
        $this->assertSame(['Excel'], $topics(['search' => 'ana']));
        $this->assertSame(['Canva'], $topics(['search' => 'luis@']));
        // An unknown stage or kind is every request.
        $this->assertCount(3, $topics(['stage' => 'read', 'kind' => 'fax']));

        // The counts follow the search, never the stage chosen.
        $this->get('/es/editor/requests?stage=guide&search=excel')
            ->assertInertia(fn ($page) => $page
                ->where('filters', ['stage' => 'guide', 'kind' => null, 'search' => 'excel'])
                ->where('stageCounts', ['pending' => 1, 'guide' => 1]));
    }

    public function test_the_editor_shows_the_account_to_assign_the_guide_to(): void
    {
        Queue::fake();
        $this->signInAsReader();
        $this->ask()->assertSessionHasNoErrors();

        $this->signInAsOwner();

        $this->get('/es/editor/requests')
            ->assertInertia(fn ($page) => $page
                ->where('inquiries.data.0.name', 'María')
                ->where('inquiries.data.0.account_email', 'maria@gmail.com')
                ->missing('inquiries.data.0.user'));

        // An older request, from before sign-in, keeps what it typed.
        Inquiry::query()->delete();
        Inquiry::create(['locale' => 'es', 'topic' => 'Excel', 'contact' => 'ana@example.com', 'name' => 'Ana']);

        $this->get('/es/editor/requests')
            ->assertInertia(fn ($page) => $page
                ->where('inquiries.data.0.name', 'Ana')
                ->where('inquiries.data.0.account_email', null)
                ->where('inquiries.data.0.reply_url', 'mailto:ana@example.com'));
    }

    public function test_a_signed_in_account_is_recorded_with_its_request(): void
    {
        Mail::fake();
        $maria = $this->signInAsReader();

        $this->ask()->assertSessionHasNoErrors();

        // The account to assign the guide to, and the one the owner's mail answers.
        $inquiry = Inquiry::sole();
        $this->assertTrue($inquiry->user->is($maria));
        Mail::assertSent(InquiryReceivedMail::class, fn ($mail) => $mail->build()->hasReplyTo('maria@gmail.com', 'María'));
    }

    public function test_an_unverified_account_asks_but_is_not_recorded(): void
    {
        Queue::fake();
        $this->signInAsReader(['email_verified_at' => null]);

        // Its email is only a claim until verified: nothing links to it.
        $this->ask()->assertSessionHasNoErrors();

        $this->assertNull(Inquiry::sole()->user_id);
    }

    public function test_the_owner_writes_a_request_of_their_own(): void
    {
        Mail::fake();
        $this->signInAsOwner();

        $this->post('/es/editor/requests', ['topic' => 'Excel avanzado', 'contact' => 'owner@gmail.com', 'name' => 'Yo'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $inquiry = Inquiry::sole();
        $this->assertSame('es', $inquiry->locale);
        $this->assertSame(Inquiry::EMAIL, $inquiry->contact_kind);
        $this->assertNull($inquiry->user_id);
        // The owner wrote it: mailed to nobody.
        Mail::assertNothingSent();

        $this->post('/es/editor/requests', ['topic' => '', 'contact' => 'x'])->assertSessionHasErrors(['topic', 'contact']);
    }

    public function test_only_the_owner_writes_a_request_in_the_editor(): void
    {
        $this->signInAsReader();

        // A reader is never approved, so the editor's floor turns them away before `role:admin`.
        $this->post('/es/editor/requests', ['topic' => 'Excel', 'contact' => 'ana@example.com'])->assertForbidden();
        $this->assertSame(0, Inquiry::count());
    }

    public function test_the_owner_deletes_a_request(): void
    {
        $this->signInAsOwner();

        $inquiry = Inquiry::create(['locale' => 'es', 'topic' => 'Excel', 'contact' => 'ana@example.com']);

        $this->delete("/es/editor/requests/{$inquiry->id}")->assertRedirect();
        $this->assertSame(0, Inquiry::count());
    }

    public function test_another_languages_request_is_not_found_in_this_editor(): void
    {
        $this->signInAsOwner();

        $english = Inquiry::create(['locale' => 'en', 'topic' => 'English', 'contact' => 'bob@example.com']);

        $this->put("/es/editor/requests/{$english->id}/read")->assertNotFound();
        $this->delete("/es/editor/requests/{$english->id}")->assertNotFound();

        $this->assertSame(1, Inquiry::count());
    }

    public function test_only_the_owner_reads_the_requests(): void
    {
        $this->get('/es/editor/requests')->assertRedirect();

        $this->actingAs(User::factory()->create(['approved_at' => now(), 'email_verified_at' => now()]));
        $this->get('/es/editor/requests')->assertForbidden();
    }
}
