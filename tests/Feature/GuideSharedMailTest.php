<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Mail\GuideSharedMail;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A reader told by email that a guide is theirs: when added or let in, if the guide can be opened;
 * otherwise the moment it can; never twice unless the owner resends; never when the owner says not
 * to. In the guide's language, with its link and the address to sign in with.
 */
class GuideSharedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://arcadia.yhndevside.com']);
        Mail::fake();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]));
    }

    private function guide(bool $readable = true): Guide
    {
        $guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1]);

        if ($readable) {
            $guide->latestVersion()->release();
            $guide->update(['status' => GuideStatus::PUBLISHED]);
        }

        return $guide;
    }

    private function add(Guide $guide, string $email = 'ana@gmail.com', bool $notify = true)
    {
        return $this->post("/es/editor/guides/{$guide->id}/readers", ['email' => $email, 'notify' => $notify]);
    }

    public function test_a_reader_added_to_a_guide_they_can_open_is_told_at_once(): void
    {
        $guide = $this->guide();

        $this->add($guide)->assertSessionHas('success', __('arcadia.editor.readers.flash.added_and_told', ['email' => 'ana@gmail.com']));

        Mail::assertSent(GuideSharedMail::class, function (GuideSharedMail $mail) use ($guide) {
            $html = $mail->render();

            return $mail->hasTo('ana@gmail.com')
                && ! $mail->accessGranted
                && str_contains($html, 'https://arcadia.yhndevside.com/guia/'.$guide->link_token)
                && str_contains($html, 'ana@gmail.com')
                && str_contains($mail->build()->subject, 'Excel para el trabajo');
        });
        $this->assertNotNull(Reader::sole()->notified_at);
    }

    public function test_a_reader_of_a_guide_not_yet_open_waits_and_is_told_on_its_first_release(): void
    {
        $guide = $this->guide(readable: false);

        $this->add($guide)->assertSessionHas('success', __('arcadia.editor.readers.flash.added_waiting', ['email' => 'ana@gmail.com']));
        $this->add($guide, 'bob@gmail.com', notify: false);
        Mail::assertNothingSent();

        // The first release publishes it: whoever was waiting is told, and only them.
        $this->post("/es/editor/guides/{$guide->id}/versions/release");

        Mail::assertSent(GuideSharedMail::class, 1);
        Mail::assertSent(GuideSharedMail::class, fn ($mail) => $mail->hasTo('ana@gmail.com'));
        $this->assertNull($guide->readers()->where('email', 'bob@gmail.com')->sole()->notified_at);
    }

    public function test_publishing_a_released_guide_by_hand_tells_whoever_was_waiting(): void
    {
        $guide = $this->guide(readable: false);
        $guide->latestVersion()->release();
        $this->add($guide);
        Mail::assertNothingSent();

        $this->put("/es/editor/guides/{$guide->id}", ['title' => $guide->title, 'level' => 'basic', 'status' => 'published']);

        Mail::assertSent(GuideSharedMail::class, fn ($mail) => $mail->hasTo('ana@gmail.com'));
    }

    public function test_a_reader_is_told_once_unless_the_owner_resends(): void
    {
        $guide = $this->guide();
        $this->add($guide);

        // Removed and added back: already told, not told again.
        $reader = Reader::sole();
        $this->delete("/es/editor/guides/{$guide->id}/readers/{$reader->id}");
        $this->add($guide);
        Mail::assertSent(GuideSharedMail::class, 1);

        $this->post("/es/editor/guides/{$guide->id}/readers/{$reader->id}/notify")
            ->assertSessionHas('success', __('arcadia.editor.readers.flash.resent', ['email' => 'ana@gmail.com']));
        Mail::assertSent(GuideSharedMail::class, 2);
    }

    public function test_nothing_is_resent_for_a_guide_that_cannot_be_opened(): void
    {
        $guide = $this->guide(readable: false);
        $this->add($guide);

        $this->post("/es/editor/guides/{$guide->id}/readers/".Reader::sole()->id.'/notify')
            ->assertSessionHas('error', __('arcadia.editor.readers.flash.not_readable'));
        Mail::assertNothingSent();
    }

    public function test_an_accepted_request_is_told_it_has_access(): void
    {
        $guide = $this->guide();
        $other = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'bob@gmail.com']);
        $request = $guide->accessRequests()->create(['user_id' => $other->id]);

        $this->post("/es/editor/guides/{$guide->id}/access-requests/{$request->id}");

        Mail::assertSent(GuideSharedMail::class, fn ($mail) => $mail->hasTo('bob@gmail.com') && $mail->accessGranted);
    }

    public function test_the_readers_screen_says_who_was_told(): void
    {
        $guide = $this->guide();
        $this->add($guide);
        $this->add($guide, 'bob@gmail.com', notify: false);

        $this->get("/es/editor/guides/{$guide->id}/readers")
            ->assertInertia(fn ($page) => $page
                ->where('readable', true)
                ->whereNot('readers.0.notified_at', null)
                ->where('readers.1.notify', false)
                ->where('readers.1.notified_at', null));
    }
}
