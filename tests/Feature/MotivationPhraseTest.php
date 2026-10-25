<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\PhraseMoment;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Phrase;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GuidePhraseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Short motivation phrases: per language and moment, seeded twenty a language, shown to a reader
 * on "My guides" — one welcoming them, one per guide for where they stand — from the guide's own
 * choice when it made one, and kept by the owner in the editor.
 */
class MotivationPhraseTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $item;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->item = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Una', 'body' => 'x', 'position' => 1]);
        $this->guide->latestVersion()->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com', 'name' => 'Ana Pérez']);
        $this->reader = $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id, 'name' => 'María']);
    }

    private function signInAsOwner(): void
    {
        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now()]));
    }

    public function test_twenty_phrases_a_language_are_seeded_once_across_every_moment(): void
    {
        $this->seed(GuidePhraseSeeder::class);
        $this->seed(GuidePhraseSeeder::class);

        foreach (['es', 'en'] as $locale) {
            $this->assertSame(20, Phrase::query()->where('locale', $locale)->count());

            foreach (PhraseMoment::cases() as $moment) {
                $this->assertTrue(Phrase::query()->where('locale', $locale)->where('moment', $moment)->exists(), "{$locale} {$moment->value}");
            }
        }

        // Short enough to read in a glance.
        $this->assertLessThanOrEqual(60, Phrase::query()->get()->max(fn (Phrase $phrase) => mb_strlen($phrase->text)));
    }

    public function test_a_guides_moment_follows_its_progress(): void
    {
        $this->assertSame(PhraseMoment::START, PhraseMoment::forProgress(0, 8));
        $this->assertSame(PhraseMoment::PROGRESS, PhraseMoment::forProgress(3, 8));
        $this->assertSame(PhraseMoment::ALMOST, PhraseMoment::forProgress(6, 8));
        $this->assertSame(PhraseMoment::DONE, PhraseMoment::forProgress(8, 8));
    }

    public function test_a_phrase_takes_the_readers_name_or_closes_up_without_one(): void
    {
        $phrase = new Phrase(['text' => '¡Vas bien, {name}!']);

        $this->assertSame('¡Vas bien, María!', $phrase->forReader('María'));
        $this->assertSame('¡Vas bien!', $phrase->forReader(null));
    }

    public function test_my_guides_welcomes_the_reader_and_cheers_each_guide_for_where_they_stand(): void
    {
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::WELCOME, 'text' => 'Hola de nuevo, {name}.']);
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::START, 'text' => 'Dale play.']);
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::DONE, 'text' => '¡Lo lograste, {name}!']);
        Phrase::create(['locale' => 'en', 'moment' => PhraseMoment::START, 'text' => 'Press play.']);

        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page
                ->where('welcome', 'Hola de nuevo, María.')
                ->where('guides.0.phrase', 'Dale play.'));

        $this->reader->tick($this->item->key, true);

        $this->get('/es/mis-guias')->assertInertia(fn ($page) => $page->where('guides.0.phrase', '¡Lo lograste, María!'));
    }

    public function test_a_guide_that_chose_its_phrases_draws_only_on_those(): void
    {
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::START, 'text' => 'Una de todas.']);
        $own = Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::START, 'text' => 'La de esta guía.']);
        $this->guide->phrases()->attach($own);

        foreach (range(1, 5) as $visit) {
            $this->actingAs($this->ana)->get('/es/mis-guias')
                ->assertInertia(fn ($page) => $page->where('guides.0.phrase', 'La de esta guía.'));
        }
    }

    public function test_with_no_phrase_for_the_moment_nothing_is_shown(): void
    {
        $this->actingAs($this->ana)->get('/es/mis-guias')
            ->assertInertia(fn ($page) => $page->where('welcome', null)->where('guides.0.phrase', null));
    }

    public function test_the_guide_page_pops_up_a_phrase_once_per_guide_per_session(): void
    {
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::START, 'text' => 'Dale play, {name}.']);
        $url = "/guia/{$this->guide->link_token}";

        $this->actingAs($this->ana)->get($url)->assertInertia(fn ($page) => $page->where('motivation', 'Dale play, María.'));

        // Back to the same guide in the same session: nothing to pop up again.
        $this->get($url)->assertInertia(fn ($page) => $page->where('motivation', null));
        $this->get($url)->assertInertia(fn ($page) => $page->where('motivation', null));

        // Another guide of theirs still gets its own.
        $other = Guide::create(['locale' => 'es', 'title' => 'Word', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $other->latestVersion()->release();
        $other->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $this->ana->id, 'name' => 'María']);

        $this->get("/guia/{$other->link_token}")->assertInertia(fn ($page) => $page->where('motivation', 'Dale play, María.'));

        // A new session, a new popup.
        $this->flushSession();
        $this->actingAs($this->ana)->get($url)->assertInertia(fn ($page) => $page->where('motivation', 'Dale play, María.'));
    }

    public function test_the_guide_page_pops_up_nothing_for_the_owner_or_without_a_phrase(): void
    {
        $url = "/guia/{$this->guide->link_token}";

        // No phrase for the moment: nothing, and the next visit may still get one once there is.
        $this->actingAs($this->ana)->get($url)->assertInertia(fn ($page) => $page->where('motivation', null));
        Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::START, 'text' => 'Dale play.']);
        $this->get($url)->assertInertia(fn ($page) => $page->where('motivation', 'Dale play.'));

        $this->signInAsOwner();
        $this->flushSession();
        $this->get($url)->assertInertia(fn ($page) => $page->where('motivation', null));
    }

    public function test_the_owner_keeps_this_languages_phrases_and_chooses_a_guides(): void
    {
        $this->signInAsOwner();

        $this->post('/es/editor/phrases', ['moment' => 'start', 'text' => '  Arranca hoy.  '])->assertSessionHasNoErrors();
        $phrase = Phrase::sole();
        $this->assertSame(['es', 'Arranca hoy.'], [$phrase->locale, $phrase->text]);

        $this->put("/es/editor/phrases/{$phrase->id}", ['moment' => 'progress', 'text' => 'Sigue así.']);
        $this->assertSame(PhraseMoment::PROGRESS, $phrase->fresh()->moment);

        $this->post('/es/editor/phrases', ['moment' => 'start', 'text' => str_repeat('x', Phrase::MAX_LENGTH + 1)])->assertSessionHasErrors('text');
        $this->post('/es/editor/phrases', ['moment' => 'whenever', 'text' => 'x'])->assertSessionHasErrors('moment');

        // A guide chooses it in its form; another language's phrase is refused.
        $english = Phrase::create(['locale' => 'en', 'moment' => PhraseMoment::START, 'text' => 'Go.']);
        $fields = ['title' => 'Excel', 'level' => 'basic', 'status' => 'published'];
        $this->put("/es/editor/guides/{$this->guide->id}", [...$fields, 'phrase_ids' => [$english->id]])->assertSessionHasErrors('phrase_ids.0');

        // The welcome greets every guide of a reader's at once, so no guide chooses it.
        $welcome = Phrase::create(['locale' => 'es', 'moment' => PhraseMoment::WELCOME, 'text' => 'Hola.']);
        $this->put("/es/editor/guides/{$this->guide->id}", [...$fields, 'phrase_ids' => [$welcome->id]])->assertSessionHasErrors('phrase_ids.0');
        $this->get("/es/editor/guides/{$this->guide->id}/edit")->assertInertia(fn ($page) => $page
            ->where('phrases', fn ($phrases) => collect($phrases)->doesntContain('moment', 'welcome'))
            ->where('moments', fn ($moments) => collect($moments)->doesntContain('value', 'welcome')));
        $welcome->delete();
        $this->put("/es/editor/guides/{$this->guide->id}", [...$fields, 'phrase_ids' => [$phrase->id]])->assertSessionHasNoErrors();
        $this->assertSame([$phrase->id], $this->guide->phrases()->pluck('guide_phrases.id')->all());

        $this->get('/es/editor/phrases')->assertInertia(fn ($page) => $page
            ->component('editor/phrases/Index')
            ->has('phrases', 1)
            ->where('phrases.0.guides_count', 1));

        // Not this editor's language: not found.
        $this->delete("/es/editor/phrases/{$english->id}")->assertNotFound();

        $this->delete("/es/editor/phrases/{$phrase->id}")->assertRedirect();
        $this->assertSame(0, $this->guide->phrases()->count());
    }
}
