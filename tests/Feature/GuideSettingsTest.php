<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\GuideSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The price a guide is offered at: $3 until the owner says otherwise, set per host in the
 * editor's settings, and shown on that host's landing page.
 */
class GuideSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsOwner(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    public function test_a_guide_is_offered_at_three_dollars_by_default(): void
    {
        $this->get('/es')->assertInertia(fn ($page) => $page->where('price', '$3'));
    }

    public function test_the_owner_sets_this_hosts_price_and_its_landing_page_shows_it(): void
    {
        $this->signInAsOwner();

        $this->get('/es/editor/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('editor/settings/Edit')->where('price', '3.00'));

        $this->put('/es/editor/settings', ['price' => '2.5'])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/es')->assertInertia(fn ($page) => $page->where('price', '$2.50'));

        // The other host keeps its own.
        $this->get('/en')->assertInertia(fn ($page) => $page->where('price', '$3'));
    }

    public function test_a_short_note_shows_beside_the_price_and_blank_clears_it(): void
    {
        $this->signInAsOwner();

        $this->get('/es')->assertInertia(fn ($page) => $page->where('priceNote', null));

        $this->put('/es/editor/settings', ['price' => '3', 'price_note' => ' BCV '])->assertSessionHasNoErrors();

        $this->get('/es/editor/settings')->assertInertia(fn ($page) => $page->where('priceNote', 'BCV'));
        $this->get('/es')->assertInertia(fn ($page) => $page->where('price', '$3')->where('priceNote', 'BCV'));
        // Per host, like the price.
        $this->get('/en')->assertInertia(fn ($page) => $page->where('priceNote', null));

        $this->put('/es/editor/settings', ['price' => '3', 'price_note' => '']);
        $this->assertNull(Setting::forLocale('es')->price_note);

        $this->put('/es/editor/settings', ['price' => '3', 'price_note' => str_repeat('x', 25)])
            ->assertSessionHasErrors('price_note');
    }

    public function test_the_owner_sets_how_long_a_step_takes_and_a_prompt_starts_at_it(): void
    {
        $this->signInAsOwner();

        $this->get('/es/editor/settings')->assertInertia(fn ($page) => $page->where('stepMin', 4)->where('stepMax', 8));
        $this->assertSame('4 a 8', Setting::forLocale('es')->stepLengthLabel());

        $this->put('/es/editor/settings', ['price' => '3', 'step_min_minutes' => 3, 'step_max_minutes' => 6])->assertSessionHasNoErrors();
        $this->assertSame('3 a 6', Setting::forLocale('es')->fresh()->stepLengthLabel());

        // A range runs forwards, and is saved whole.
        $this->put('/es/editor/settings', ['price' => '3', 'step_min_minutes' => 8, 'step_max_minutes' => 4])->assertSessionHasErrors('step_max_minutes');
        $this->put('/es/editor/settings', ['price' => '3', 'step_min_minutes' => 5])->assertSessionHasErrors('step_max_minutes');
        $this->put('/es/editor/settings', ['price' => '3', 'step_min_minutes' => 0, 'step_max_minutes' => 4])->assertSessionHasErrors('step_min_minutes');

        // Saving the price alone keeps it.
        $this->put('/es/editor/settings', ['price' => '4'])->assertSessionHasNoErrors();
        $this->assertSame('3 a 6', Setting::forLocale('es')->fresh()->stepLengthLabel());
    }

    public function test_a_price_is_a_dollar_amount(): void
    {
        $this->signInAsOwner();

        foreach (['tres', '-1', '1000', '2.555'] as $price) {
            $this->put('/es/editor/settings', ['price' => $price])->assertSessionHasErrors('price');
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_blank_or_zero_is_no_price_and_the_landing_page_states_none(): void
    {
        $this->signInAsOwner();

        foreach (['', '0'] as $price) {
            $this->put('/es/editor/settings', ['price' => $price, 'price_note' => 'BCV'])->assertSessionHasNoErrors();

            $this->assertSame('0.00', Setting::forLocale('es')->price);
            $this->get('/es')->assertInertia(fn ($page) => $page->where('price', null)->where('priceNote', null));
        }
    }

    public function test_only_the_owner_sets_the_price(): void
    {
        $this->put('/es/editor/settings', ['price' => '1'])->assertRedirect();

        $this->actingAs(User::factory()->create(['approved_at' => now(), 'email_verified_at' => now()]));
        $this->put('/es/editor/settings', ['price' => '1'])->assertForbidden();

        $this->assertSame(0, Setting::count());
    }

    public function test_seeding_never_overwrites_a_price_the_owner_set(): void
    {
        Setting::create(['locale' => 'es', 'price' => '5']);

        $this->seed(GuideSettingsSeeder::class);
        $this->seed(GuideSettingsSeeder::class);

        $this->assertSame('5.00', Setting::forLocale('es')->price);
        $this->assertSame('3.00', Setting::forLocale('en')->price);
        $this->assertSame(2, Setting::count());
    }
}
