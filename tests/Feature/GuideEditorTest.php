<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Jobs\IndexGuideJob;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The editor's first screens: a site's guides and its three vocabularies. Each guide host edits
 * its own language only — the Spanish editor never lists, opens or files under English.
 */
class GuideEditorTest extends TestCase
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

    private function guide(string $locale = 'es', string $title = 'Excel para el trabajo'): Guide
    {
        return Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC]);
    }

    public function test_the_list_shows_only_this_sites_language(): void
    {
        $this->guide('es', 'Excel para el trabajo')->latestVersion()->release();
        $this->guide('en', 'Excel for work');

        $this->get('/es/editor/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/guides/Index')
                ->where('guides.total', 1)
                ->where('guides.data.0.title', 'Excel para el trabajo')
                ->where('guides.data.0.versions_count', 1)
                ->where('guides.data.0.released_versions_count', 1)
                ->where('guides.per_page', 12)
                ->where('guides.data.0.cover', null)
                ->missing('guides.data.0.images'));
    }

    public function test_the_list_filters_by_status_and_counts_each(): void
    {
        $this->guide('es', 'Borrador');
        $this->guide('es', 'Publicada')->update(['status' => GuideStatus::PUBLISHED]);

        $this->get('/es/editor/guides?status=published')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('status', 'published')
                ->where('guides.total', 1)
                ->where('guides.data.0.title', 'Publicada')
                ->where('statusCounts', ['draft' => 1, 'published' => 1]));

        // An unknown status is no filter at all.
        $this->get('/es/editor/guides?status=archived')
            ->assertInertia(fn ($page) => $page->where('status', null)->where('guides.total', 2));
    }

    public function test_the_list_searches_the_title_and_the_category_and_counts_within_it(): void
    {
        $category = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $this->guide('es', 'Excel para el trabajo');
        $this->guide('es', 'Correo sin estrés')->update(['category_id' => $category->id]);
        $this->guide('es', 'Fotografía')->update(['status' => GuideStatus::PUBLISHED]);

        $this->get('/es/editor/guides?search=excel')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('search', 'excel')
                ->where('guides.total', 1)
                ->where('guides.data.0.title', 'Excel para el trabajo')
                ->where('statusCounts', ['draft' => 1]));

        $this->get('/es/editor/guides?search=ofim')
            ->assertInertia(fn ($page) => $page->where('guides.total', 1)->where('guides.data.0.title', 'Correo sin estrés'));

        // Blank is no search at all.
        $this->get('/es/editor/guides?search=%20')
            ->assertInertia(fn ($page) => $page->where('search', null)->where('guides.total', 3));
    }

    public function test_a_guide_is_created_in_the_sites_language_with_its_terms(): void
    {
        $category = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $topic = Topic::create(['locale' => 'es', 'name' => 'Fórmulas']);

        $this->post('/es/editor/guides', [
            'title' => 'Excel para el trabajo',
            'level' => 'intermediate',
            'status' => 'draft',
            'category_id' => $category->id,
            'topic_ids' => [$topic->id],
            // Its own words, one per line in the form: tidied, nothing to register first.
            'goals' => ['  Conseguir trabajo en ventas ', '', 'conseguir trabajo en ventas', 'Pasar la prueba de Excel'],
        ])->assertRedirect(route('es.editor.guides.index'));

        $guide = Guide::sole();
        $this->assertSame('es', $guide->locale);
        $this->assertSame(Level::INTERMEDIATE, $guide->level);
        $this->assertSame(GuideStatus::DRAFT, $guide->status);
        $this->assertTrue($guide->category->is($category));
        $this->assertSame([$topic->id], $guide->topics->modelKeys());
        $this->assertSame(['Conseguir trabajo en ventas', 'Pasar la prueba de Excel'], $guide->goals);
        // Version 1 comes with it.
        $this->assertSame(1, $guide->latestVersion()->number);
    }

    public function test_a_guide_cannot_be_filed_under_the_other_languages_terms(): void
    {
        $englishTopic = Topic::create(['locale' => 'en', 'name' => 'Formulas']);

        $this->post('/es/editor/guides', [
            'title' => 'Excel',
            'level' => 'basic',
            'status' => 'draft',
            'topic_ids' => [$englishTopic->id],
        ])->assertSessionHasErrors('topic_ids.0');

        $this->assertSame(0, Guide::count());
    }

    public function test_a_guide_has_a_handful_of_goals_at_most(): void
    {
        $this->post('/es/editor/guides', [
            'title' => 'Excel',
            'level' => 'basic',
            'status' => 'draft',
            'goals' => array_map(fn ($n) => "Meta {$n}", range(1, Guide::MAX_GOALS + 1)),
        ])->assertSessionHasErrors('goals');

        $this->assertSame(0, Guide::count());
    }

    public function test_a_guide_is_updated_and_its_terms_replaced(): void
    {
        $guide = $this->guide();
        $old = Topic::create(['locale' => 'es', 'name' => 'Fórmulas']);
        $new = Topic::create(['locale' => 'es', 'name' => 'Tablas dinámicas']);
        $guide->topics()->attach($old);

        $this->put("/es/editor/guides/{$guide->id}", [
            'title' => 'Excel avanzado',
            'level' => 'advanced',
            'status' => 'published',
            'topic_ids' => [$new->id],
        ])->assertRedirect();

        $guide->refresh();
        $this->assertSame('Excel avanzado', $guide->title);
        $this->assertSame(GuideStatus::PUBLISHED, $guide->status);
        $this->assertSame([$new->id], $guide->topics->modelKeys());
    }

    public function test_the_other_languages_guide_cannot_be_opened_or_changed_here(): void
    {
        $english = $this->guide('en', 'Excel for work');

        $this->get("/es/editor/guides/{$english->id}/edit")->assertNotFound();
        $this->delete("/es/editor/guides/{$english->id}")->assertNotFound();
        $this->assertNotNull($english->fresh());
    }

    public function test_deleting_a_guide_takes_its_versions_with_it(): void
    {
        $guide = $this->guide();

        $this->delete("/es/editor/guides/{$guide->id}")->assertRedirect();

        $this->assertNull($guide->fresh());
        $this->assertDatabaseCount('guide_versions', 0);
    }

    public function test_vocabulary_terms_are_added_renamed_and_deleted_in_this_language(): void
    {
        $this->post('/es/editor/vocabulary/categories', ['name' => 'Ofimática'])->assertRedirect();

        $category = Category::sole();
        $this->assertSame(['es', 'ofimatica'], [$category->locale, $category->slug]);

        $this->put("/es/editor/vocabulary/categories/{$category->id}", ['name' => 'Oficina'])->assertRedirect();
        $this->assertSame(['Oficina', 'oficina'], [$category->fresh()->name, $category->fresh()->slug]);

        $this->delete("/es/editor/vocabulary/categories/{$category->id}")->assertRedirect();
        $this->assertNull($category->fresh());
    }

    public function test_a_term_name_is_unique_within_its_language_only(): void
    {
        Category::create(['locale' => 'es', 'name' => 'Excel']);
        Category::create(['locale' => 'en', 'name' => 'Office']);

        // "excel" is the same slug as "Excel": refused.
        $this->post('/es/editor/vocabulary/categories', ['name' => 'excel'])->assertSessionHasErrors('slug');

        // The English list's "Office" does not block a Spanish one.
        $this->post('/es/editor/vocabulary/categories', ['name' => 'Office'])->assertSessionHasNoErrors();
    }

    public function test_an_unknown_vocabulary_is_not_a_route(): void
    {
        $this->post('/es/editor/vocabulary/levels', ['name' => 'x'])->assertNotFound();
        // Goals are written on the guide now (ADR 0020), not registered.
        $this->post('/es/editor/vocabulary/goals', ['name' => 'x'])->assertNotFound();
    }

    public function test_the_vocabulary_screen_counts_how_many_guides_use_each_term(): void
    {
        $topic = Topic::create(['locale' => 'es', 'name' => 'Fórmulas']);
        $this->guide()->topics()->attach($topic);

        $this->get('/es/editor/vocabulary')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/vocabulary/Index')
                ->where('lists.topics.0.guides_count', 1)
                ->has('lists.categories', 0)
                ->missing('lists.goals'));
    }

    public function test_the_categorizer_searches_every_guide_of_this_language(): void
    {
        $category = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $this->guide('es', 'Excel para el trabajo')->update(['category_id' => $category->id]);
        $this->guide('es', 'Ahorra un dólar al día');
        $this->guide('en', 'Excel at work');

        $this->get('/es/editor/vocabulary')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('guides', 2)
                ->where('guides.0.title', 'Ahorra un dólar al día')
                ->where('guides.0.category_id', null)
                ->where('guides.1.category_id', $category->id));
    }

    public function test_the_categorizer_files_several_guides_at_once_and_reindexes_each(): void
    {
        config(['knowledge.index_on_change' => true]);
        $category = Category::create(['locale' => 'es', 'name' => 'Salud']);
        $walking = $this->guide('es', '30 días de caminar');
        $stress = $this->guide('es', 'Calma el estrés');
        Queue::fake();

        $this->post("/es/editor/vocabulary/categories/{$category->id}/guides", ['guide_ids' => [$walking->id, $stress->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame([$category->id, $category->id], [$walking->fresh()->category_id, $stress->fresh()->category_id]);
        Queue::assertPushed(IndexGuideJob::class, 2);
    }

    public function test_the_categorizer_moves_a_guide_from_another_category_only_when_asked(): void
    {
        $health = Category::create(['locale' => 'es', 'name' => 'Salud']);
        $habits = Category::create(['locale' => 'es', 'name' => 'Hábitos']);
        $guide = $this->guide('es', 'Recupera tu tiempo');
        $guide->update(['category_id' => $habits->id]);

        $this->post("/es/editor/vocabulary/categories/{$health->id}/guides", ['guide_ids' => [$guide->id]])
            ->assertSessionHasErrors('guide_ids');
        $this->assertSame($habits->id, $guide->fresh()->category_id);

        $this->post("/es/editor/vocabulary/categories/{$health->id}/guides", ['guide_ids' => [$guide->id], 'move' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame($health->id, $guide->fresh()->category_id);

        // Filing it again where it already is is not a move.
        $this->post("/es/editor/vocabulary/categories/{$health->id}/guides", ['guide_ids' => [$guide->id]])
            ->assertSessionHasNoErrors();
    }

    public function test_the_categorizer_stays_within_this_language(): void
    {
        $spanish = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $english = Category::create(['locale' => 'en', 'name' => 'Office']);
        $englishGuide = $this->guide('en', 'Excel at work');

        $this->post("/es/editor/vocabulary/categories/{$spanish->id}/guides", ['guide_ids' => [$englishGuide->id]])
            ->assertSessionHasErrors('guide_ids.0');
        $this->post("/es/editor/vocabulary/categories/{$english->id}/guides", ['guide_ids' => [$this->guide()->id]])
            ->assertNotFound();

        $this->assertNull($englishGuide->fresh()->category_id);
    }

    public function test_the_categorizer_unfiles_one_guide_and_leaves_it_in_place(): void
    {
        $category = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $guide = $this->guide();
        $guide->update(['category_id' => $category->id]);
        $other = Category::create(['locale' => 'es', 'name' => 'Ventas']);

        // Only from the category it is in.
        $this->delete("/es/editor/vocabulary/categories/{$other->id}/guides/{$guide->id}")->assertNotFound();

        $this->delete("/es/editor/vocabulary/categories/{$category->id}/guides/{$guide->id}")->assertRedirect();

        $this->assertNull($guide->fresh()->category_id);
    }

    public function test_stages_unlock_in_order_until_the_owner_switches_it_off(): void
    {
        $this->post('/es/editor/guides', ['title' => 'Excel', 'level' => 'basic', 'status' => 'draft'])->assertRedirect();
        $guide = Guide::query()->sole();
        $this->assertTrue($guide->unlock_in_order);

        $this->put("/es/editor/guides/{$guide->id}", ['title' => 'Excel', 'level' => 'basic', 'status' => 'draft', 'unlock_in_order' => false])->assertRedirect();
        $this->assertFalse($guide->fresh()->unlock_in_order);

        $this->get("/es/editor/guides/{$guide->id}/edit")
            ->assertInertia(fn ($page) => $page->where('guide.unlock_in_order', false));

        // A save that does not send it leaves it as it was.
        $this->put("/es/editor/guides/{$guide->id}", ['title' => 'Excel', 'level' => 'basic', 'status' => 'draft'])->assertRedirect();
        $this->assertFalse($guide->fresh()->unlock_in_order);
    }
}
