<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Category;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue lists only the guides anyone may ask for (ADR 0023): published, released and open
 * for enrollment, in the language of its URL. A guide made for one person is never open, so it is
 * never listed. A card carries what the presentation shows and nothing of the content.
 */
class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function guide(string $title, string $locale = 'es', bool $open = true, bool $released = true, GuideStatus $status = GuideStatus::PUBLISHED, ?Category $category = null): Guide
    {
        $guide = Guide::create(['locale' => $locale, 'title' => $title, 'level' => Level::BASIC, 'category_id' => $category?->id]);
        $guide->update(['status' => $status, 'open_for_enrollment' => $open, 'pitch' => "Sobre {$title}", 'goals' => ['Una meta']]);

        if ($released) {
            $guide->latestVersion()->release();
        }

        return $guide;
    }

    public function test_it_lists_only_published_released_guides_open_for_enrollment(): void
    {
        $listed = $this->guide('Excel para el trabajo');
        $this->guide('Hecha para una persona', open: false);
        $this->guide('Sin versión liberada', released: false);
        $this->guide('En borrador', status: GuideStatus::DRAFT);
        $this->guide('English guide', locale: 'en');

        $this->get('/es/guias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catalogue')
                ->has('guides', 1)
                ->where('guides.0.title', 'Excel para el trabajo')
                ->where('guides.0.url', '/guia/'.$listed->link_token)
                ->where('guides.0.pitch', 'Sobre Excel para el trabajo')
                ->where('guides.0.goals', ['Una meta'])
                ->missing('guides.0.greeting')
                ->where('noIndex', true));
    }

    public function test_the_english_catalogue_lists_the_english_guides(): void
    {
        $this->guide('Excel para el trabajo');
        $this->guide('English for interviews', locale: 'en');

        $this->get('/en/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('guides', 1)
                ->where('guides.0.title', 'English for interviews')
                ->where('alternates.es', fn ($url) => str_ends_with($url, '/es/guias')));
    }

    public function test_only_categories_a_listed_guide_is_in_are_offered(): void
    {
        $office = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $unused = Category::create(['locale' => 'es', 'name' => 'Diseño']);
        $this->guide('Excel para el trabajo', category: $office);
        $this->guide('Canva privado', open: false, category: $unused);

        $this->get('/es/guias')
            ->assertInertia(fn ($page) => $page
                ->where('categories', [['id' => $office->id, 'name' => 'Ofimática']])
                ->where('guides.0.category', $office->id));
    }

    public function test_the_landing_page_explores_the_catalogue(): void
    {
        $this->get('/es')
            ->assertInertia(fn ($page) => $page->where('catalogueUrl', '/es/guias'));
    }
}
