<?php

namespace Tests\Feature;

use App\Enums\Accent;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Enums\Palette;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A signed-in reader's own pages: their guides in this language, each in their colours, and the
 * colours changed from there or from the guide page — their own assignment only, with no
 * approval asked of them.
 */
class ReaderDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        // A reader is never approved: the membership is their admission.
        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create([
            'email' => 'ana@gmail.com',
            'approved_at' => null,
        ]);
    }

    private function guide(string $title, bool $readable = true, string $locale = 'es'): Guide
    {
        $guide = Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC]);

        if ($readable) {
            $guide->update(['status' => GuideStatus::PUBLISHED]);
            $guide->latestVersion()->release();
        }

        return $guide;
    }

    private function assign(Guide $guide, ?User $user = null): Reader
    {
        $user ??= $this->ana;

        return $guide->readers()->create(['email' => $user->email, 'user_id' => $user->id]);
    }

    public function test_a_reader_sees_their_own_guides_in_this_language(): void
    {
        $excel = $this->guide('Excel para el trabajo');
        $canva = $this->guide('Canva', readable: false);
        $english = $this->guide('Excel for work', locale: 'en');
        $theirs = $this->guide('Word');

        $this->assign($excel)->paint(Palette::FOREST, Accent::AMBER, Reader::BY_ADMIN);
        $this->assign($canva);
        $this->assign($english);
        $this->assign($theirs, User::factory()->create());

        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reader/Dashboard')
                ->where('email', 'ana@gmail.com')
                ->has('guides', 2)
                ->where('guides.0.title', 'Excel para el trabajo')
                ->where('guides.0.palette', 'forest')
                ->where('guides.0.accent', 'amber')
                ->where('guides.0.url', fn ($url) => str_ends_with($url, '/guia/'.$excel->link_token))
                // Assigned but not yet released: listed, with no way in.
                ->where('guides.1.title', 'Canva')
                ->where('guides.1.url', null)
                ->where('noIndex', true));
    }

    public function test_the_streak_counts_days_in_a_row_on_caracas_time_across_their_guides(): void
    {
        $excel = $this->assign($this->guide('Excel'));
        $canva = $this->assign($this->guide('Canva'));

        // A tick on Sunday, then a day missed. 02:00 UTC on Wednesday is 22:00 on Tuesday in
        // Caracas: counted as Wednesday, the run would be one day, not two.
        $this->travelTo('2026-10-18 15:00:00');
        $excel->tick('a', true);
        $this->travelTo('2026-10-21 02:00:00');
        $canva->tick('b', true);
        $this->travelTo('2026-10-21 15:00:00');
        $excel->tick('c', true);
        $excel->tick('d', true);

        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('streak', ['days' => 2, 'today' => true]));
    }

    public function test_the_streak_stands_until_a_whole_day_passes_without_a_tick(): void
    {
        $reader = $this->assign($this->guide('Excel'));

        $this->travelTo('2026-10-20 15:00:00');
        $reader->tick('a', true);

        // The next day, before ticking: yesterday still counts, and today is to do.
        $this->travelTo('2026-10-21 15:00:00');
        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('streak', ['days' => 1, 'today' => false]));

        // A day missed ends it.
        $this->travelTo('2026-10-22 15:00:00');
        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('streak', ['days' => 0, 'today' => false]));
    }

    public function test_unticking_a_line_takes_its_day_out_of_the_streak(): void
    {
        $reader = $this->assign($this->guide('Excel'));

        $this->travelTo('2026-10-20 15:00:00');
        $reader->tick('a', true);
        $this->travelTo('2026-10-21 15:00:00');
        $reader->tick('b', true);
        $reader->tick('a', false);

        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('streak', ['days' => 1, 'today' => true]));
    }

    public function test_a_reader_removed_from_every_guide_still_gets_an_empty_list(): void
    {
        $this->assign($this->guide('Excel'))->delete();

        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('guides', 0));
    }

    public function test_only_a_reader_reaches_it(): void
    {
        $this->get('/es/mis-guias')->assertRedirect('/login');

        $admin = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $this->actingAs($admin)->get('/es/mis-guias')->assertForbidden();

        // An account with no membership has no role here.
        $this->actingAs(User::factory()->create())->get('/es/mis-guias')->assertForbidden();
    }

    public function test_a_reader_gets_help_with_the_support_address_and_their_device_limit(): void
    {
        $this->assign($this->guide('Excel'));

        $this->actingAs($this->ana)->get('/es/ayuda')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reader/Help')
                ->where('contactEmail', config('arcadia.contact_email'))
                ->where('deviceLimit', 3));

        $this->get('/en/help')->assertOk();
    }

    public function test_only_a_reader_reaches_help(): void
    {
        $this->get('/es/ayuda')->assertRedirect('/login');

        $admin = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);
        $this->actingAs($admin)->get('/es/ayuda')->assertForbidden();
    }

    public function test_a_reader_saves_their_own_colours_and_the_badge_reads_reader(): void
    {
        $reader = $this->assign($this->guide('Excel'));

        $this->actingAs($this->ana)
            ->put("/es/mis-guias/{$reader->id}/appearance", ['palette' => 'plum', 'accent' => 'pink'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $reader->refresh();
        $this->assertSame(Palette::PLUM, $reader->palette);
        $this->assertSame(Accent::PINK, $reader->accent);
        $this->assertSame(Reader::BY_READER, $reader->appearance_by);
    }

    public function test_a_reader_can_paint_only_their_own_assignment_and_only_from_the_catalogue(): void
    {
        $bob = User::factory()->withRole(Role::ALIAS_READER)->create();
        $bobs = $this->assign($this->guide('Excel'), $bob);
        $english = $this->assign($this->guide('Excel for work', locale: 'en'));
        $mine = $this->assign($this->guide('Canva'));

        $this->actingAs($this->ana);

        $this->put("/es/mis-guias/{$bobs->id}/appearance", ['palette' => 'plum', 'accent' => 'pink'])->assertNotFound();
        $this->put("/es/mis-guias/{$english->id}/appearance", ['palette' => 'plum', 'accent' => 'pink'])->assertNotFound();
        $this->put("/es/mis-guias/{$mine->id}/appearance", ['palette' => '#000000', 'accent' => 'pink'])->assertSessionHasErrors('palette');

        $this->assertNull($bobs->fresh()->appearance_by);
        $this->assertNull($mine->fresh()->appearance_by);
    }

    public function test_the_guide_page_offers_its_reader_their_colours(): void
    {
        $guide = $this->guide('Excel');
        $guide->update(['default_palette' => Palette::GRAPHITE]);
        $this->assign($guide)->paint(Palette::FOREST, Accent::AMBER, Reader::BY_READER);

        $this->actingAs($this->ana)->get('/guia/'.$guide->link_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('appearance.--guide-ground', Palette::FOREST->colours()['ground'])
                ->where('readerAppearance.palette', 'forest')
                ->where('readerAppearance.accent', 'amber')
                ->where('readerAppearance.saveUrl', fn ($url) => str_ends_with($url, '/es/mis-guias/'.$guide->readers()->first()->id.'/appearance')));
    }

    public function test_the_owner_reads_any_guide_in_its_default_and_with_no_controls(): void
    {
        $guide = $this->guide('Excel');
        $guide->update(['default_palette' => Palette::GRAPHITE]);
        $owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]);

        $this->actingAs($owner)->get('/guia/'.$guide->link_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('appearance.--guide-ground', Palette::GRAPHITE->colours()['ground'])
                ->where('readerAppearance', null)
                ->where('myGuidesUrl', null));
    }
}
