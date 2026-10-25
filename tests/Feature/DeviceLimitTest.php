<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Http\Middleware\RecogniseDevice;
use App\Models\Device;
use App\Models\Guide;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Devices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * How many browsers one account may read Arcadia on (option D, D1): each is recognised by its
 * cookie, a new one takes a free slot, at the limit the reader swaps one out once a month or asks,
 * an idle one frees its slot, and the owner revokes a device or sets an account's own limit.
 */
class DeviceLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

    private Guide $guide;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        config(['arcadia.device_limit' => 3]);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);
        $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id]);
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    /** Ana opening the guide in a browser with no session — a new one, unless it carries a cookie. */
    private function openInNewBrowser(?string $cookie = null): TestResponse
    {
        $this->flushSession();
        $this->actingAs($this->ana);

        $request = $cookie ? $this->withCookie(RecogniseDevice::COOKIE, $cookie) : $this;

        return $request->withHeader('User-Agent', self::PHONE)->get($this->link());
    }

    private function fillTheSlots(): void
    {
        foreach (range(1, 3) as $browser) {
            $this->openInNewBrowser()->assertOk();
        }
    }

    public function test_a_first_browser_becomes_a_device_and_is_recognised_after(): void
    {
        $response = $this->openInNewBrowser()->assertOk()->assertCookie(RecogniseDevice::COOKIE);

        $device = Device::sole();
        $this->assertSame('Chrome · Android', $device->label);
        $this->assertTrue($device->user->is($this->ana));
        // Only its hash is kept.
        $this->assertNotSame($response->getCookie(RecogniseDevice::COOKIE)->getValue(), $device->token_hash);

        // The same session, and later a new session in the same browser: still one device.
        $this->get($this->link())->assertOk();
        $this->openInNewBrowser($response->getCookie(RecogniseDevice::COOKIE, decrypt: true)->getValue())->assertOk();

        $this->assertSame(1, Device::count());
    }

    public function test_a_browser_past_the_limit_is_sent_to_choose_and_comes_back_after_swapping(): void
    {
        $this->fillTheSlots();

        $this->openInNewBrowser()->assertRedirect('/es/dispositivos');
        $this->assertSame(3, Device::count());

        $this->get('/es/dispositivos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reader/DeviceLimit')
                ->has('devices', 3)
                ->where('limit', 3)
                ->where('canReplace', true));

        $out = Device::query()->oldest('id')->first();

        $this->put("/es/dispositivos/{$out->id}")->assertRedirect($this->link());

        $this->assertModelMissing($out);
        $this->assertSame(3, Device::count());
        $this->assertNotNull(Membership::where('user_id', $this->ana->id)->value('devices_replaced_at'));
        $this->get($this->link())->assertOk();
    }

    public function test_a_reader_swaps_a_device_only_once_a_month(): void
    {
        $this->fillTheSlots();
        $this->openInNewBrowser();
        $this->put('/es/dispositivos/'.Device::query()->oldest('id')->value('id'));

        $this->openInNewBrowser()->assertRedirect('/es/dispositivos');
        $this->get('/es/dispositivos')->assertInertia(fn ($page) => $page->where('canReplace', false));
        $this->put('/es/dispositivos/'.Device::query()->oldest('id')->value('id'))->assertForbidden();

        $this->travel(31)->days();
        $this->get('/es/dispositivos')->assertInertia(fn ($page) => $page->where('canReplace', true));
    }

    public function test_a_device_unused_for_ninety_days_frees_its_slot(): void
    {
        $this->fillTheSlots();
        Device::query()->oldest('id')->first()->update(['last_used_at' => now()->subDays(91)]);

        $this->openInNewBrowser()->assertOk();

        // The idle one is forgotten, not merely skipped.
        $this->assertSame(3, Device::count());
        $this->assertSame(0, Device::query()->where('last_used_at', '<', now()->subDays(90))->count());
    }

    public function test_a_revoked_device_is_signed_out_on_its_next_request(): void
    {
        $this->openInNewBrowser()->assertOk();
        $device = Device::sole();

        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $this->actingAs($owner)->delete("/es/editor/accounts/{$this->ana->id}/devices/{$device->id}")->assertRedirect();
        $this->assertModelMissing($device);

        // Back in Ana's browser: its session still names the device.
        $this->actingAs($this->ana)->get($this->link())
            ->assertRedirect('/login')
            ->assertSessionHas('error', __('arcadia.devices.signed_out'));
        $this->assertGuest();
    }

    public function test_the_owner_sets_an_accounts_own_limit(): void
    {
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->put("/es/editor/accounts/{$this->ana->id}/device-limit", ['limit' => 4])->assertRedirect();
        $this->fillTheSlots();
        $this->openInNewBrowser()->assertOk();
        $this->assertSame(4, Device::count());

        // Blank returns it to the site's.
        $this->actingAs($owner)->put("/es/editor/accounts/{$this->ana->id}/device-limit", ['limit' => null]);
        $this->assertNull(Membership::where('user_id', $this->ana->id)->value('device_limit'));

        $this->actingAs($owner)->put("/es/editor/accounts/{$this->ana->id}/device-limit", ['limit' => 0])
            ->assertSessionHasErrors('limit');
    }

    public function test_the_owner_manages_only_arcadias_accounts_and_is_never_counted(): void
    {
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $noMembership = User::factory()->create();

        $this->actingAs($owner)->put("/es/editor/accounts/{$noMembership->id}/device-limit", ['limit' => 5])->assertNotFound();

        // The owner reads the guide without becoming a device.
        $this->actingAs($owner)->get($this->link())->assertOk();
        $this->assertSame(0, Device::count());
    }

    public function test_the_readers_screen_lists_each_readers_devices_and_limit(): void
    {
        $this->openInNewBrowser();
        $this->guide->readers()->create(['email' => 'bob@gmail.com']);

        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->get("/es/editor/guides/{$this->guide->id}/readers")
            ->assertInertia(fn ($page) => $page
                ->where('deviceLimit', 3)
                ->where('readers.0.email', 'ana@gmail.com')
                ->where('readers.0.devices.0.label', 'Chrome · Android')
                ->missing('readers.0.devices.0.token_hash')
                ->where('readers.0.device_limit', null)
                ->where('readers.1.user_id', null)
                ->has('readers.1.devices', 0));
    }

    public function test_a_device_is_labelled_by_browser_and_system(): void
    {
        $labels = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1' => 'Safari · iPhone',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0' => 'Edge · Windows',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36' => 'Chrome · Mac',
            'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/126.0 Mobile Safari/537.36 WhatsApp/2.24' => 'WhatsApp · Android',
        ];

        foreach ($labels as $userAgent => $label) {
            $this->assertSame($label, Devices::label($userAgent));
        }

        $this->assertSame(__('arcadia.devices.unknown'), Devices::label(''));
    }
}
