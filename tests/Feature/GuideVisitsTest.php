<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What a guide's presentation brings in (ADR 0022): a visit counted once per browser session per
 * day, never for its readers, the owner or a crawler, and nothing kept about the visitor; requests
 * and approvals counted from the requests; all shown on the editor home, over 30 days or ever.
 */
class GuideVisitsTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]);
        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
        $this->guide->update(['status' => GuideStatus::PUBLISHED, 'open_for_enrollment' => true]);
        $this->guide->latestVersion()->release();
    }

    private function link(): string
    {
        return '/guia/'.$this->guide->link_token;
    }

    private function visits(): int
    {
        return (int) DB::table('guide_visits')->where('guide_id', $this->guide->id)->sum('visits');
    }

    public function test_a_visitor_is_counted_once_a_day_and_nothing_about_them_is_kept(): void
    {
        $this->get($this->link())->assertOk();
        $this->get($this->link())->assertOk();

        $this->assertSame(1, $this->visits());
        $this->assertSame(['id', 'guide_id', 'day', 'visits'], array_keys((array) DB::table('guide_visits')->first()));

        // Another browser — another session — is another visit.
        $this->flushSession();
        $this->get($this->link())->assertOk();
        $this->assertSame(2, $this->visits());

        // The next day, the same browser counts again.
        $this->travel(1)->days();
        $this->get($this->link())->assertOk();
        $this->assertSame(3, $this->visits());
        $this->assertSame(2, DB::table('guide_visits')->where('guide_id', $this->guide->id)->count());
    }

    public function test_readers_the_owner_and_crawlers_are_not_counted(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['email_verified_at' => now()]);
        $this->guide->readers()->create(['email' => $reader->email, 'user_id' => $reader->id]);

        $this->actingAs($reader)->get($this->link())->assertOk();
        $this->actingAs($this->owner)->get($this->link())->assertOk();
        $this->actingAs($this->owner)->get("/es/editor/guides/{$this->guide->id}/presentation")->assertOk();
        auth()->logout();
        $this->flushSession();
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get($this->link())->assertOk();
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get($this->link())->assertOk();

        $this->assertSame(0, $this->visits());
    }

    public function test_a_removed_reader_is_not_counted(): void
    {
        $removed = User::factory()->withRole(Role::ALIAS_READER)->create(['email_verified_at' => now()]);
        $this->guide->readers()->create(['email' => $removed->email, 'user_id' => $removed->id])->delete();

        $this->actingAs($removed)->get($this->link())->assertOk();

        $this->assertSame(0, $this->visits());
    }

    public function test_the_editor_home_shows_visits_requests_and_enrollments_by_window(): void
    {
        DB::table('guide_visits')->insert([
            ['guide_id' => $this->guide->id, 'day' => now()->subDays(40)->toDateString(), 'visits' => 5],
            ['guide_id' => $this->guide->id, 'day' => now()->subDays(2)->toDateString(), 'visits' => 3],
        ]);
        $ana = User::factory()->withRole(Role::ALIAS_READER)->create();
        $bob = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->guide->accessRequests()->create(['user_id' => $ana->id])->decide(AccessRequestStatus::APPROVED, $this->owner);
        $old = $this->guide->accessRequests()->create(['user_id' => $bob->id]);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        // A closed guide nobody visited is left out.
        Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC]);

        $this->actingAs($this->owner)->get('/es/editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('window', '30')
                ->has('presentations', 1)
                ->where('presentations.0.title', 'Excel para el trabajo')
                ->where('presentations.0.visits', 3)
                ->where('presentations.0.requests', 1)
                ->where('presentations.0.enrollments', 1));

        $this->get('/es/editor?window=all')
            ->assertInertia(fn ($page) => $page
                ->where('window', 'all')
                ->where('presentations.0.visits', 8)
                ->where('presentations.0.requests', 2));
    }

    public function test_each_guides_card_shows_its_own_figures_over_30_days(): void
    {
        DB::table('guide_visits')->insert([
            ['guide_id' => $this->guide->id, 'day' => now()->subDays(40)->toDateString(), 'visits' => 5],
            ['guide_id' => $this->guide->id, 'day' => now()->toDateString(), 'visits' => 2],
        ]);
        $other = Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC]);
        DB::table('guide_visits')->insert(['guide_id' => $other->id, 'day' => now()->toDateString(), 'visits' => 9]);
        $ana = User::factory()->withRole(Role::ALIAS_READER)->create();
        $this->guide->accessRequests()->create(['user_id' => $ana->id])->decide(AccessRequestStatus::APPROVED, $this->owner);

        $this->actingAs($this->owner)->get('/es/editor/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('guides.data', fn ($guides) => collect($guides)->firstWhere('id', $this->guide->id)['presentation'] === ['visits' => 2, 'requests' => 1, 'enrollments' => 1]
                    && collect($guides)->firstWhere('id', $other->id)['presentation'] === ['visits' => 9, 'requests' => 0, 'enrollments' => 0]));

        // The Presentation screen no longer carries them.
        $this->get("/es/editor/guides/{$this->guide->id}/images")
            ->assertInertia(fn ($page) => $page->missing('figures')->missing('window'));
    }

    public function test_the_privacy_note_says_what_is_counted(): void
    {
        $this->get('/privacidad')->assertOk()->assertInertia(fn ($page) => $page->component('Privacy'));

        $this->assertStringContainsString('una vez por navegador y día', __('arcadia.privacy.sections.visits.body', [], 'es'));
    }
}
