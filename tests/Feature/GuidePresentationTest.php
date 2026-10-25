<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Device;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a guide's link shows someone who may not read it (ADR 0022): what it is about — cover,
 * title, goals, pitch — with its own link preview, never what is in it, never indexed, never a
 * price; and no device slot spent on looking.
 */
class GuidePresentationTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GuideImage::DISK);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $this->guide->update([
            'status' => GuideStatus::PUBLISHED,
            'goals' => ['Armar una tabla dinámica'],
            'pitch' => 'Aprende Excel en dos semanas, con videos gratuitos ordenados por etapas.',
            'greeting' => '¡Hola {nombre}! Esta guía es para ti.',
        ]);
        $this->guide->latestVersion()->sections()->create(['kind' => 'how_to_use', 'title' => 'Secreto del contenido', 'body' => 'x', 'position' => 1]);
        $this->guide->latestVersion()->release();
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    private function owner(): User
    {
        return User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]);
    }

    public function test_a_signed_out_visitor_sees_what_it_is_about_and_none_of_its_content(): void
    {
        $this->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GuidePresentation')
                ->where('state', 'sign_in')
                ->where('guide.title', 'Excel para el trabajo')
                ->where('guide.goals', ['Armar una tabla dinámica'])
                ->where('guide.pitch', 'Aprende Excel en dos semanas, con videos gratuitos ordenados por etapas.')
                ->where('guide.cover', null)
                ->where('noIndex', true)
                ->where('metaDescription', 'Aprende Excel en dos semanas, con videos gratuitos ordenados por etapas.')
                // Arcadia's own preview until the guide has a cover.
                ->where('ogImage', '/img/arcadia/og-arcadia-es.jpg')
                ->missing('sections')
                ->missing('stages')
                ->missing('guide.greeting'))
            ->assertDontSee('Secreto del contenido')
            ->assertDontSee('Esta guía es para ti');
    }

    public function test_with_a_cover_the_link_previews_as_the_guide(): void
    {
        $this->actingAs($this->owner())
            ->post("/es/editor/guides/{$this->guide->id}/images/cover", ['image' => UploadedFile::fake()->image('cover.png', 1536, 1024)]);
        auth()->logout();
        $image = $this->guide->images()->sole();

        $this->get($this->link())
            ->assertInertia(fn ($page) => $page
                ->where('ogImage', $image->url('share'))
                ->where('ogImageAlt', 'Excel para el trabajo')
                ->where('guide.cover.url', $image->url('hero')));
    }

    public function test_a_draft_or_a_wrong_link_is_still_not_found(): void
    {
        $this->guide->update(['status' => GuideStatus::DRAFT]);

        $this->get($this->link())->assertNotFound();
        $this->get('/guia/'.str_repeat('a', 24))->assertNotFound();
    }

    public function test_looking_at_it_spends_no_device_but_reading_it_does(): void
    {
        $stranger = User::factory()->withRole(Role::ALIAS_READER)->create(['email_verified_at' => now()]);
        $this->actingAs($stranger)->get($this->link())->assertOk()->assertInertia(fn ($page) => $page->component('GuidePresentation'));
        $this->assertSame(0, Device::count());

        $this->guide->readers()->create(['email' => $stranger->email, 'user_id' => $stranger->id]);
        $this->get($this->link())->assertOk()->assertInertia(fn ($page) => $page->component('Guide'));
        $this->assertSame(1, Device::where('user_id', $stranger->id)->count());
    }

    public function test_the_owner_writes_the_pitch_and_previews_the_page(): void
    {
        $this->actingAs($this->owner())
            ->put("/es/editor/guides/{$this->guide->id}/pitch", ['pitch' => '  Nueva presentación  '])
            ->assertRedirect();
        $this->assertSame('Nueva presentación', $this->guide->fresh()->pitch);

        $this->put("/es/editor/guides/{$this->guide->id}/pitch", ['pitch' => str_repeat('a', Guide::MAX_PITCH + 1)])
            ->assertSessionHasErrors('pitch');

        $this->get("/es/editor/guides/{$this->guide->id}/presentation")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GuidePresentation')
                ->where('preview', true)
                ->where('state', 'open')
                ->where('accessUrl', null)
                ->where('guide.pitch', 'Nueva presentación'));

        $this->put("/es/editor/guides/{$this->guide->id}/pitch", ['pitch' => ''])->assertRedirect();
        $this->assertNull($this->guide->fresh()->pitch);
    }
}
