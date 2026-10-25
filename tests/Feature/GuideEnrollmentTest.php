<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Jobs\SendAccessRequestNotificationJob;
use App\Mail\AccessRequestedMail;
use App\Models\AccessRequest;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Enrollment (ADR 0022): the owner opens a guide for it; an account it is not shared with asks
 * from the link, once, with an optional message; the owner is emailed, arranges payment outside
 * Arcadia, and approves or declines — keeping his own note on the request. Nothing is granted
 * until he approves, and a reader he adds himself still reads at once.
 */
class GuideEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $bob;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]);
        $this->bob = User::factory()->withRole(Role::ALIAS_READER)->create(['name' => 'Bob Díaz', 'email' => 'bob@gmail.com', 'email_verified_at' => now()]);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $this->guide->update(['status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    private function editor(string $path = ''): string
    {
        return "/es/editor/guides/{$this->guide->id}".$path;
    }

    public function test_a_guide_starts_closed_and_the_owner_opens_and_closes_it(): void
    {
        $this->assertFalse($this->guide->fresh()->open_for_enrollment);

        $this->actingAs($this->owner)->get($this->editor('/readers'))
            ->assertInertia(fn ($page) => $page->where('openForEnrollment', false));

        $this->put($this->editor('/enrollment'), ['open' => true])->assertRedirect();
        $this->assertTrue($this->guide->fresh()->open_for_enrollment);

        $this->put($this->editor('/enrollment'), ['open' => false])->assertRedirect();
        $this->assertFalse($this->guide->fresh()->open_for_enrollment);
    }

    public function test_a_request_emails_the_owner_once_with_the_requesters_email_to_reply_to(): void
    {
        Queue::fake();
        $this->guide->update(['open_for_enrollment' => true]);

        $this->actingAs($this->bob)->post($this->link().'/acceso', ['message' => "Hola\nquiero inscribirme"])->assertRedirect();
        $this->post($this->link().'/acceso')->assertRedirect();

        Queue::assertPushed(SendAccessRequestNotificationJob::class, 1);

        $mail = new AccessRequestedMail(AccessRequest::sole());
        $mail->assertHasReplyTo('bob@gmail.com');
        $mail->assertSeeInHtml('Excel para el trabajo');
        $mail->assertSeeInHtml('mailto:bob@gmail.com');
        $mail->assertSeeInHtml('quiero inscribirme');
        $mail->assertSeeInHtml("/es/editor/guides/{$this->guide->id}/readers");
    }

    public function test_the_job_sends_the_mail_to_the_owners_address(): void
    {
        Mail::fake();
        config(['mail.business_address' => 'owner@yhndevside.com']);
        $request = $this->guide->accessRequests()->create(['user_id' => $this->bob->id]);

        (new SendAccessRequestNotificationJob($request))->handle();

        Mail::assertSent(AccessRequestedMail::class, fn ($mail) => $mail->hasTo('owner@yhndevside.com'));
    }

    public function test_a_message_has_a_limit(): void
    {
        $this->guide->update(['open_for_enrollment' => true]);

        $this->actingAs($this->bob)->post($this->link().'/acceso', ['message' => str_repeat('a', AccessRequest::MAX_MESSAGE + 1)])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_a_reader_or_a_removed_reader_cannot_ask(): void
    {
        $this->guide->update(['open_for_enrollment' => true]);
        $this->guide->readers()->create(['email' => $this->bob->email, 'user_id' => $this->bob->id])->delete();

        $this->actingAs($this->bob)->get($this->link())
            ->assertInertia(fn ($page) => $page->where('state', 'removed')->where('accessUrl', null));
        $this->post($this->link().'/acceso')->assertForbidden();

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_a_pending_request_grants_nothing(): void
    {
        $this->guide->update(['open_for_enrollment' => true]);
        $this->guide->accessRequests()->create(['user_id' => $this->bob->id]);

        $this->actingAs($this->bob)->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('GuidePresentation')->where('state', 'pending')->missing('stages'));
    }

    public function test_adding_the_email_by_hand_answers_its_request_and_reads_at_once(): void
    {
        $request = $this->guide->accessRequests()->create(['user_id' => $this->bob->id]);

        $this->actingAs($this->owner)->post($this->editor('/readers'), ['email' => 'bob@gmail.com'])->assertSessionHasNoErrors();

        $this->assertSame(AccessRequestStatus::APPROVED, $request->fresh()->status);
        $this->actingAs($this->bob)->get($this->link())->assertOk();
    }

    public function test_the_owner_keeps_a_note_on_a_request_that_the_requester_never_sees(): void
    {
        $this->guide->update(['open_for_enrollment' => true]);
        $request = $this->guide->accessRequests()->create(['user_id' => $this->bob->id, 'message' => 'Hola']);

        $this->actingAs($this->owner)->put($this->editor("/access-requests/{$request->id}/note"), ['owner_note' => '  Pagó el 20, ref 1234  '])
            ->assertRedirect();
        $this->assertSame('Pagó el 20, ref 1234', $request->fresh()->owner_note);

        $this->get($this->editor('/readers'))
            ->assertInertia(fn ($page) => $page
                ->where('accessRequests.0.status', 'pending')
                ->where('accessRequests.0.message', 'Hola')
                ->where('accessRequests.0.owner_note', 'Pagó el 20, ref 1234'));

        $this->actingAs($this->bob)->get($this->link())->assertDontSee('Pagó el 20');

        $this->actingAs($this->owner)->put($this->editor("/access-requests/{$request->id}/note"), ['owner_note' => ''])->assertRedirect();
        $this->assertNull($request->fresh()->owner_note);
    }

    public function test_an_answered_request_cannot_be_answered_again(): void
    {
        $request = $this->guide->accessRequests()->create(['user_id' => $this->bob->id]);
        $request->decide(AccessRequestStatus::DECLINED, $this->owner);

        $this->actingAs($this->owner)->post($this->editor("/access-requests/{$request->id}"))->assertNotFound();
        $this->delete($this->editor("/access-requests/{$request->id}"))->assertNotFound();

        $this->assertSame(0, $this->guide->readers()->count());
    }

    public function test_the_editor_home_counts_pending_requests_in_its_language(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel at work', 'level' => Level::BASIC]);
        $carla = User::factory()->withRole(Role::ALIAS_READER)->create();

        $this->guide->accessRequests()->create(['user_id' => $this->bob->id]);
        $this->guide->accessRequests()->create(['user_id' => $carla->id])->decide(AccessRequestStatus::DECLINED, $this->owner);
        $english->accessRequests()->create(['user_id' => $this->bob->id]);

        $this->actingAs($this->owner)->get('/es/editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.enrollments.pending', 1));
    }

    public function test_another_languages_request_is_not_this_editors(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel at work', 'level' => Level::BASIC]);
        $request = $english->accessRequests()->create(['user_id' => $this->bob->id]);

        $this->actingAs($this->owner)
            ->put("/es/editor/guides/{$english->id}/access-requests/{$request->id}/note", ['owner_note' => 'x'])
            ->assertNotFound();
        $this->put("/es/editor/guides/{$english->id}/enrollment", ['open' => true])->assertNotFound();
    }
}
