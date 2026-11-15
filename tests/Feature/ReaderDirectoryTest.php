<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Everyone this language's guides are for, one row per email, each with the guides it was given.
 */
class ReaderDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    private function guide(string $title, string $locale = 'es'): Guide
    {
        return Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC]);
    }

    public function test_one_row_per_email_counts_its_guides_in_this_language_only(): void
    {
        $excel = $this->guide('Excel');
        $savings = $this->guide('Ahorro');
        $savings->update(['status' => GuideStatus::PUBLISHED]);
        $removed = $this->guide('Caminar');
        $english = $this->guide('Excel at work', 'en');

        $ana = User::factory()->create(['email' => 'ana@gmail.com', 'name' => 'Ana Pérez']);
        Reader::create(['guide_id' => $excel->id, 'email' => 'ana@gmail.com', 'user_id' => $ana->id]);
        Reader::create(['guide_id' => $savings->id, 'email' => 'ana@gmail.com', 'user_id' => $ana->id]);
        Reader::create(['guide_id' => $removed->id, 'email' => 'ana@gmail.com', 'user_id' => $ana->id])->delete();
        Reader::create(['guide_id' => $english->id, 'email' => 'ana@gmail.com', 'user_id' => $ana->id]);
        Reader::create(['guide_id' => $excel->id, 'email' => 'bob@gmail.com']);
        Reader::create(['guide_id' => $english->id, 'email' => 'carl@gmail.com']);

        $this->get('/es/editor/readers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/readers/Index')
                ->where('totals', ['readers' => 2, 'with_account' => 1, 'pending' => 1])
                ->has('readers.data', 2)
                ->where('readers.data', fn ($rows) => collect($rows)->firstWhere('email', 'ana@gmail.com')['guides_count'] === 2
                    && collect($rows)->firstWhere('email', 'ana@gmail.com')['name'] === 'Ana Pérez'
                    && collect(collect($rows)->firstWhere('email', 'ana@gmail.com')['guides'])->pluck('title')->all() === ['Excel', 'Ahorro']
                    && collect($rows)->firstWhere('email', 'bob@gmail.com')['has_account'] === false));
    }

    public function test_the_search_matches_an_email_or_a_name(): void
    {
        $excel = $this->guide('Excel');
        Reader::create(['guide_id' => $excel->id, 'email' => 'ana@gmail.com', 'name' => 'Ana']);
        Reader::create(['guide_id' => $excel->id, 'email' => 'bob@gmail.com']);

        $this->get('/es/editor/readers?search=bob')
            ->assertInertia(fn ($page) => $page
                ->has('readers.data', 1)
                ->where('readers.data.0.email', 'bob@gmail.com')
                ->where('totals.readers', 2));

        $this->get('/es/editor/readers?search=Ana')
            ->assertInertia(fn ($page) => $page->has('readers.data', 1)->where('readers.data.0.email', 'ana@gmail.com'));
    }

    public function test_the_access_filter_separates_readers_with_an_account_from_those_pending(): void
    {
        $excel = $this->guide('Excel');
        $savings = $this->guide('Ahorro');
        $ana = User::factory()->create(['email' => 'ana@gmail.com']);
        Reader::create(['guide_id' => $excel->id, 'email' => 'ana@gmail.com', 'user_id' => $ana->id]);
        $bob = Reader::create(['guide_id' => $excel->id, 'email' => 'bob@gmail.com', 'notify' => true]);
        $bob->forceFill(['notified_at' => now()])->save();
        Reader::create(['guide_id' => $savings->id, 'email' => 'bob@gmail.com']);

        $this->get('/es/editor/readers?access=pending')
            ->assertInertia(fn ($page) => $page
                ->has('readers.data', 1)
                ->where('readers.data.0.email', 'bob@gmail.com')
                ->where('readers.data.0.guides_count', 2)
                ->whereNot('readers.data.0.guides.0.notified_at', null)
                ->where('readers.data.0.guides.1.notified_at', null)
                ->where('filters.access', 'pending')
                ->where('totals', ['readers' => 2, 'with_account' => 1, 'pending' => 1]));

        $this->get('/es/editor/readers?access=account')
            ->assertInertia(fn ($page) => $page->has('readers.data', 1)->where('readers.data.0.email', 'ana@gmail.com'));

        // Anything else is every reader.
        $this->get('/es/editor/readers?access=nope')
            ->assertInertia(fn ($page) => $page->has('readers.data', 2)->where('filters.access', null));
    }

    public function test_a_reader_cannot_see_who_else_reads(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create(['approved_at' => now(), 'email_verified_at' => now()]));

        $this->get('/es/editor/readers')->assertForbidden();
    }
}
