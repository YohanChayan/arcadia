<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\ImageSlot;
use App\Enums\Level;
use App\Enums\PromptSectionKind;
use App\Models\Category;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Models\PromptTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoverPromptTemplateSeeder;
use Database\Seeders\GuideFlyerPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A guide's images, uploaded in the editor: one cover, replaced in place, and several flyers,
 * added side by side — each held to its slot's rule, with the crops every surface needs cut from
 * it, and removed with all its files. Its readers
 * see the cover atop the guide and its square on their "My guides" card.
 */
class GuideImageTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GuideImage::DISK);

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function imagesUrl(?Guide $guide = null, ?string $slot = null): string
    {
        return '/es/editor/guides/'.($guide ?? $this->guide)->id.'/images'.($slot ? "/{$slot}" : '');
    }

    private function upload(UploadedFile $file, ?Guide $guide = null)
    {
        return $this->post($this->imagesUrl($guide, 'cover'), ['image' => $file]);
    }

    /** @return array{0: int, 1: int} */
    private function sizeOf(string $path): array
    {
        return array_slice(getimagesizefromstring(Storage::disk(GuideImage::DISK)->get($path)), 0, 2);
    }

    public function test_a_cover_keeps_the_original_and_cuts_every_crop(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024))->assertSessionHasNoErrors();

        $image = $this->guide->images()->sole();

        $this->assertSame(ImageSlot::COVER, $image->slot);
        $this->assertSame([1536, 1024], [$image->width, $image->height]);
        $this->assertStringStartsWith("guides/{$this->guide->id}/", $image->directory);
        $this->assertSame([1536, 1024], $this->sizeOf($image->path()));
        $this->assertSame([1200, 630], $this->sizeOf($image->path('share')));
        // The whole frame, scaled down to the hero's width.
        $this->assertSame([1536, 1024], $this->sizeOf($image->path('hero')));
        $this->assertSame([320, 320], $this->sizeOf($image->path('thumb')));
    }

    public function test_a_wide_cover_is_scaled_down_for_the_hero(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.jpg', 1920, 1080))->assertSessionHasNoErrors();

        $this->assertSame([1600, 900], $this->sizeOf($this->guide->images()->sole()->path('hero')));
    }

    public function test_the_editor_shows_each_slot_with_its_rule_and_its_image(): void
    {
        $this->get($this->imagesUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/guides/Images')
                ->where('guide.title', 'Excel para el trabajo')
                ->where('slots.0.slot', 'cover')
                ->where('slots.0.min_width', 1200)
                ->where('slots.0.max_images', 1)
                ->where('slots.0.images', [])
                ->where('slots.1.slot', 'flyer')
                ->where('slots.1.min_width', 1024)
                ->where('slots.1.max_images', ImageSlot::FLYER->maxImages()));

        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024));
        $image = $this->guide->images()->sole();

        $this->get($this->imagesUrl())
            ->assertInertia(fn ($page) => $page
                ->where('slots.0.images.0.id', $image->id)
                ->where('slots.0.images.0.crops.share', '/storage/'.$image->directory.'/share.jpg')
                ->where('slots.0.images.0.original', '/storage/'.$image->directory.'/original.png'));
    }

    public function test_uploading_again_replaces_the_image_and_all_its_files(): void
    {
        $this->upload(UploadedFile::fake()->image('first.png', 1536, 1024));
        $first = $this->guide->images()->sole()->directory;

        $this->upload(UploadedFile::fake()->image('second.jpg', 1920, 1080))->assertSessionHasNoErrors();
        $second = $this->guide->images()->sole();

        $this->assertNotSame($first, $second->directory);
        $this->assertSame(1920, $second->width);
        Storage::disk(GuideImage::DISK)->assertMissing($first);
        Storage::disk(GuideImage::DISK)->assertExists($second->path('thumb'));
    }

    public function test_an_image_outside_the_rule_is_refused_and_nothing_is_kept(): void
    {
        // Too small for the share card without upscaling.
        $this->upload(UploadedFile::fake()->image('small.png', 1024, 683))->assertSessionHasErrors('image');
        // Square: its share card would need upscaling.
        $this->upload(UploadedFile::fake()->image('square.png', 1536, 1536))->assertSessionHasErrors('image');
        // Wider than a share card.
        $this->upload(UploadedFile::fake()->image('banner.png', 2400, 1000))->assertSessionHasErrors('image');
        // Not an image.
        $this->upload(UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'))->assertSessionHasErrors('image');
        // Over the size limit.
        $this->upload(UploadedFile::fake()->image('huge.png', 1536, 1024)->size(ImageSlot::MAX_KILOBYTES + 1))->assertSessionHasErrors('image');

        $this->assertSame(0, $this->guide->images()->count());
        $this->assertSame([], Storage::disk(GuideImage::DISK)->allFiles());
    }

    private function uploadFlyer(int $width = 1080, int $height = 1350, ?Guide $guide = null): TestResponse
    {
        return $this->post($this->imagesUrl($guide, 'flyer'), ['image' => UploadedFile::fake()->image('flyer.png', $width, $height)]);
    }

    public function test_flyers_are_added_beside_each_other_and_kept_whole(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024));
        $this->uploadFlyer(1536, 1920)->assertSessionHasNoErrors();
        $this->uploadFlyer(1080, 1080)->assertSessionHasNoErrors();
        $this->uploadFlyer(1080, 1920)->assertSessionHasNoErrors();
        // An image generator's square and portrait, a little under a post's 1080: never scaled up.
        $this->uploadFlyer(1024, 1024)->assertSessionHasNoErrors();
        $this->uploadFlyer(1024, 1536)->assertSessionHasNoErrors();

        $flyers = $this->guide->images()->where('slot', ImageSlot::FLYER)->oldest('id')->get();

        $this->assertCount(5, $flyers);
        $this->assertCount(5, $flyers->pluck('directory')->unique());
        // The whole frame, scaled down to a post's width: nothing cut from it.
        $this->assertSame([1080, 1350], $this->sizeOf($flyers[0]->path('post')));
        $this->assertSame([1080, 1920], $this->sizeOf($flyers[2]->path('post')));
        $this->assertSame([1024, 1536], $this->sizeOf($flyers[4]->path('post')));
        $this->assertSame(ImageSlot::COVER, $this->guide->cover()->slot);

        $this->get($this->imagesUrl())
            ->assertInertia(fn ($page) => $page
                ->has('slots.0.images', 1)
                ->has('slots.1.images', 5)
                ->where('slots.1.images.0.id', $flyers[0]->id)
                ->where('slots.1.images.2.crops.post', $flyers[2]->url('post')));
    }

    public function test_uploading_a_cover_again_still_replaces_it_while_flyers_add_up(): void
    {
        $this->upload(UploadedFile::fake()->image('first.png', 1536, 1024));
        $this->uploadFlyer();
        $this->upload(UploadedFile::fake()->image('second.jpg', 1920, 1080));
        $this->uploadFlyer();

        $this->assertSame(1, $this->guide->images()->where('slot', ImageSlot::COVER)->count());
        $this->assertSame(2, $this->guide->images()->where('slot', ImageSlot::FLYER)->count());
    }

    public function test_a_landscape_or_small_flyer_is_refused(): void
    {
        foreach ([[1536, 1024], [1000, 1000], [800, 1422]] as [$width, $height]) {
            $this->uploadFlyer($width, $height)->assertSessionHasErrors('image');
        }

        $this->assertSame(0, $this->guide->images()->count());
    }

    public function test_a_full_flyer_slot_refuses_another_until_one_is_removed(): void
    {
        foreach (range(1, ImageSlot::FLYER->maxImages()) as $ignored) {
            $this->uploadFlyer()->assertSessionHasNoErrors();
        }

        $this->uploadFlyer()->assertSessionHasErrors('image');
        $this->assertSame(ImageSlot::FLYER->maxImages(), $this->guide->images()->count());

        $this->delete($this->imagesUrl(slot: (string) $this->guide->images()->first()->id))->assertSessionHasNoErrors();
        $this->uploadFlyer()->assertSessionHasNoErrors();
    }

    public function test_removing_one_flyer_keeps_the_others(): void
    {
        $this->uploadFlyer();
        $this->uploadFlyer();
        [$first, $second] = $this->guide->images()->oldest('id')->get();

        $this->delete($this->imagesUrl(slot: (string) $first->id))->assertSessionHasNoErrors();

        $this->assertSame([$second->id], $this->guide->images()->pluck('id')->all());
        Storage::disk(GuideImage::DISK)->assertMissing($first->directory);
        Storage::disk(GuideImage::DISK)->assertExists($second->path('post'));
    }

    public function test_another_guides_image_cannot_be_removed_through_this_one(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Word', 'level' => Level::BASIC]);
        $this->uploadFlyer(guide: $other);
        $image = $other->images()->sole();

        $this->delete($this->imagesUrl(slot: (string) $image->id))->assertNotFound();
        $this->assertTrue($image->exists());
        Storage::disk(GuideImage::DISK)->assertExists($image->path());
    }

    public function test_removing_an_image_deletes_its_files(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024));
        $directory = $this->guide->images()->sole()->directory;

        $this->delete($this->imagesUrl(slot: (string) $this->guide->images()->sole()->id))->assertSessionHasNoErrors();

        $this->assertSame(0, $this->guide->images()->count());
        Storage::disk(GuideImage::DISK)->assertMissing($directory);
    }

    public function test_deleting_a_guide_deletes_its_images_files(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024));
        $directory = $this->guide->images()->sole()->directory;

        $this->guide->delete();

        Storage::disk(GuideImage::DISK)->assertMissing($directory);
    }

    public function test_an_unknown_slot_or_another_languages_guide_is_not_found(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel at work', 'level' => Level::BASIC]);

        $this->post($this->imagesUrl(slot: 'poster'), ['image' => UploadedFile::fake()->image('cover.png', 1536, 1024)])->assertNotFound();
        $this->get($this->imagesUrl($english))->assertNotFound();
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024), $english)->assertNotFound();
    }

    public function test_a_reader_cannot_reach_the_images_screen(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_READER)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->get($this->imagesUrl())->assertForbidden();
        $this->upload(UploadedFile::fake()->image('cover.png', 1536, 1024))->assertForbidden();
        $this->assertSame(0, $this->guide->images()->count());
    }

    public function test_its_readers_see_the_cover_atop_the_guide_and_on_their_card(): void
    {
        $this->upload(UploadedFile::fake()->image('cover.jpg', 1920, 1080))->assertSessionHasNoErrors();
        $this->guide->update(['status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();
        $image = $this->guide->images()->sole();

        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com', 'email_verified_at' => now()]);
        $this->guide->readers()->create(['email' => $reader->email, 'user_id' => $reader->id]);

        $this->actingAs($reader)->get('/guia/'.$this->guide->link_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guide')
                ->where('guide.cover', ['url' => $image->url('hero'), 'width' => 1600, 'height' => 900]));

        $this->get('/es/mis-guias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('guides.0.cover', $image->url('thumb')));
    }

    public function test_a_guide_without_a_cover_shows_none(): void
    {
        $this->guide->update(['status' => GuideStatus::PUBLISHED]);
        $this->guide->latestVersion()->release();

        $this->get("/es/editor/guides/{$this->guide->id}/preview")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('guide.cover', null));
    }

    public function test_the_cover_prompt_arrives_filled_from_the_guide(): void
    {
        $this->get($this->imagesUrl())->assertInertia(fn ($page) => $page->where('coverPrompt', null));

        $this->seed(CoverPromptTemplateSeeder::class);
        $category = Category::create(['locale' => 'es', 'name' => 'Ofimática']);
        $this->guide->update(['category_id' => $category->id, 'pitch' => 'Excel en dos semanas.', 'goals' => ['Armar una tabla dinámica', 'Usar BUSCARV']]);

        $this->get($this->imagesUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('coverPrompt.name', PromptTemplate::COVER)
                ->has('coverPrompt.versions', 1)
                ->where('coverPromptValues', [
                    'title' => 'Excel para el trabajo',
                    'level' => 'basic',
                    'category' => 'Ofimática',
                    'pitch' => 'Excel en dos semanas.',
                    'goals' => "Armar una tabla dinámica\nUsar BUSCARV",
                ])
                ->has('choices.category', 1));
    }

    public function test_the_cover_prompt_offers_every_version_and_the_motivation_text_lands_as_a_new_one(): void
    {
        // As the local and production databases hold it: an earlier text, already used.
        $template = PromptTemplate::create(['name' => PromptTemplate::COVER]);
        $v1 = $template->latestVersion();
        $v1->sections()->create(['kind' => PromptSectionKind::PERSONA, 'title' => 'Tu rol', 'body' => 'Adjunta la foto de referencia.', 'position' => 1]);
        $v1->freeze();

        $this->seed(CoverPromptTemplateSeeder::class);
        $this->seed(CoverPromptTemplateSeeder::class);

        $v2 = $template->latestVersion();
        $this->assertSame(2, $v2->number);
        $this->assertStringContainsString('Motivación', $v2->description);
        $this->assertStringContainsString('el momento en que sale bien', $v2->sections()->where('kind', PromptSectionKind::PERSONA)->sole()->body);
        $this->assertSame('Adjunta la foto de referencia.', $v1->sections()->sole()->body);

        $this->get($this->imagesUrl())
            ->assertInertia(fn ($page) => $page
                ->has('coverPrompt.versions', 2)
                ->where('coverPrompt.versions.0.number', 1)
                ->where('coverPrompt.versions.1.number', 2)
                ->where('coverPrompt.versions.1.description', $v2->description)
                ->has('coverPrompt.versions.1.variables', 7));
    }

    public function test_the_flyer_prompt_arrives_filled_from_the_guide_and_its_defaults(): void
    {
        $this->get($this->imagesUrl())->assertInertia(fn ($page) => $page->where('flyerPrompt', null));

        $this->seed(GuideFlyerPromptTemplateSeeder::class);
        $this->guide->update(['pitch' => 'Excel en dos semanas.', 'goals' => ['Armar una tabla dinámica', 'Usar BUSCARV']]);

        $this->get($this->imagesUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('flyerPrompt.name', PromptTemplate::GUIDE_FLYER)
                ->has('flyerPrompt.versions', 1)
                ->where('flyerPromptValues', [
                    'title' => 'Excel para el trabajo',
                    'category' => '',
                    'pitch' => 'Excel en dos semanas.',
                    'goals' => "Armar una tabla dinámica\nUsar BUSCARV",
                    'language' => 'es',
                ])
                // What the guide does not say starts at the variable's default, and the price at the host's.
                ->where('flyerPrompt.versions.0.variables', fn ($variables) => collect($variables)->firstWhere('name', 'web')['default'] === 'arcadia.yhndevside.com'
                    && collect($variables)->firstWhere('name', 'price')['default'] === '$3'));
    }
}
