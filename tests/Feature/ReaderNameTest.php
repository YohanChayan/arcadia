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
 * What a guide calls its reader: a name the owner gives when adding them, or changes later; else
 * the first name on their account. The guide page greets them by it, the editor's preview as that
 * reader does too, and so does the email that shares the guide.
 */
class ReaderNameTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://arcadia.yhndevside.com']);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'greeting' => 'Hola {nombre}, esta es tu ruta.']);
        $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1]);
        $this->guide->latestVersion()->release();
        $this->guide->update(['status' => GuideStatus::PUBLISHED]);

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $this->actingAs($this->owner);
    }

    private function readers(): string
    {
        return "/es/editor/guides/{$this->guide->id}/readers";
    }

    public function test_the_owner_names_a_reader_when_adding_them_and_changes_it_later(): void
    {
        Mail::fake();

        $this->post($this->readers(), ['email' => 'ana@gmail.com', 'name' => '  María  '])->assertSessionHasNoErrors();
        $reader = Reader::sole();
        $this->assertSame('María', $reader->name);

        // Greeted by it in the email that shares the guide.
        Mail::assertSent(GuideSharedMail::class, fn (GuideSharedMail $mail) => str_contains($mail->render(), '¡Hola, María!'));

        $this->put($this->readers()."/{$reader->id}/name", ['name' => 'Mari'])->assertSessionHasNoErrors();
        $this->assertSame('Mari', $reader->fresh()->name);

        $this->get($this->readers())->assertInertia(fn ($page) => $page
            ->where('readers.0.name', 'Mari')
            ->where('readers.0.display_name', 'Mari'));

        $this->put($this->readers()."/{$reader->id}/name", ['name' => str_repeat('x', 81)])->assertSessionHasErrors('name');
    }

    public function test_without_a_name_the_guide_uses_the_first_name_on_their_account(): void
    {
        User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com', 'name' => 'Ana Pérez']);
        $this->post($this->readers(), ['email' => 'ana@gmail.com', 'notify' => false]);
        $reader = Reader::sole();

        $this->assertNull($reader->name);
        $this->assertSame('Ana', $reader->displayName());
        $this->get($this->readers())->assertInertia(fn ($page) => $page
            ->where('readers.0.name', null)
            ->where('readers.0.display_name', 'Ana'));

        // A name given replaces it; cleared, it goes back.
        $this->put($this->readers()."/{$reader->id}/name", ['name' => 'Anita']);
        $this->assertSame('Anita', $reader->fresh()->displayName());
        $this->put($this->readers()."/{$reader->id}/name", ['name' => '']);
        $this->assertSame('Ana', $reader->fresh()->displayName());

        // No account and no name: nothing to call them.
        $this->post($this->readers(), ['email' => 'bob@gmail.com', 'notify' => false]);
        $this->assertNull(Reader::query()->where('email', 'bob@gmail.com')->sole()->displayName());
    }

    public function test_the_guide_page_greets_the_reader_by_name_and_the_owner_by_none(): void
    {
        $ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $ana->id, 'name' => 'María']);
        $link = '/guia/'.$this->guide->link_token;

        $this->get($link)->assertInertia(fn ($page) => $page->where('readerName', null));

        // The owner's preview as that reader greets them as the reader will be.
        $this->get("/es/editor/guides/{$this->guide->id}/preview?reader={$reader->id}")
            ->assertInertia(fn ($page) => $page->where('readerName', 'María'));

        $this->actingAs($ana)->get($link)->assertInertia(fn ($page) => $page
            ->where('readerName', 'María')
            ->where('guide.greeting', 'Hola {nombre}, esta es tu ruta.'));
    }
}
