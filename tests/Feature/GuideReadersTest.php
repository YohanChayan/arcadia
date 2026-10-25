<?php

namespace Tests\Feature;

use App\Enums\CardKind;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Reader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A guide's readers, assigned by email in the editor: many per guide, one row per address,
 * linked to an account when one already exists with it, and only on this language's guides.
 */
class GuideReadersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($this->owner);

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function readersUrl(?Guide $guide = null): string
    {
        return '/es/editor/guides/'.($guide ?? $this->guide)->id.'/readers';
    }

    public function test_a_guide_takes_many_readers_by_email(): void
    {
        $this->post($this->readersUrl(), ['email' => 'ana@gmail.com'])->assertSessionHasNoErrors();
        $this->post($this->readersUrl(), ['email' => 'bob@gmail.com'])->assertSessionHasNoErrors();

        $this->get($this->readersUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/guides/Readers')
                ->where('guide.title', 'Excel para el trabajo')
                ->has('readers', 2)
                ->where('readers.0.email', 'ana@gmail.com')
                ->where('readers.1.email', 'bob@gmail.com'));

        $this->assertSame($this->owner->id, Reader::firstWhere('email', 'ana@gmail.com')->assigned_by);
    }

    public function test_each_reader_shows_where_they_stand_on_the_released_version(): void
    {
        $card = $this->guide->latestVersion()->stages()->create(['title' => 'Etapa 1', 'position' => 1])
            ->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $one = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Una', 'body' => 'x', 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Dos', 'body' => 'x', 'position' => 2]);

        $ana = User::factory()->withRole(Role::ALIAS_READER)->create(['email' => 'ana@gmail.com']);
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com', 'user_id' => $ana->id]);
        $this->guide->readers()->create(['email' => 'bob@gmail.com']);

        // Nothing released: nothing to count.
        $this->get($this->readersUrl())->assertInertia(fn ($page) => $page
            ->where('released', false)
            ->where('readers.0.progress', null));

        $this->guide->latestVersion()->release();
        $reader->tick($one->fresh()->key, true);

        $this->get($this->readersUrl())->assertInertia(fn ($page) => $page
            ->where('released', true)
            ->where('readers.0.progress.done', 1)
            ->where('readers.0.progress.total', 2)
            ->where('readers.0.progress.stages.done', 0)
            ->whereNot('readers.0.progress.last_ticked_at', null)
            // No account yet: no ticks of theirs to count.
            ->where('readers.1.progress', null));
    }

    public function test_one_address_is_one_reader_whatever_its_case(): void
    {
        $this->post($this->readersUrl(), ['email' => '  Ana@Gmail.com '])->assertSessionHasNoErrors();
        $this->post($this->readersUrl(), ['email' => 'ana@gmail.com'])->assertSessionHasErrors('email');

        $this->assertSame(['ana@gmail.com'], $this->guide->readers()->pluck('email')->all());
    }

    public function test_the_same_reader_may_read_several_guides(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC]);

        $this->post($this->readersUrl(), ['email' => 'ana@gmail.com'])->assertSessionHasNoErrors();
        $this->post($this->readersUrl($other), ['email' => 'ana@gmail.com'])->assertSessionHasNoErrors();

        $this->assertSame(2, Reader::where('email', 'ana@gmail.com')->count());
    }

    public function test_an_existing_account_with_that_email_is_linked(): void
    {
        $ana = User::factory()->create(['email' => 'Ana@Gmail.com']);

        $this->post($this->readersUrl(), ['email' => 'ana@gmail.com']);
        $this->post($this->readersUrl(), ['email' => 'nobody@gmail.com']);

        $this->assertSame($ana->id, Reader::firstWhere('email', 'ana@gmail.com')->user_id);
        $this->assertNull(Reader::firstWhere('email', 'nobody@gmail.com')->user_id);

        $this->get($this->readersUrl())->assertInertia(fn ($page) => $page
            ->where('readers.0.has_account', true)
            ->where('readers.1.has_account', false));
    }

    public function test_a_bad_address_is_refused(): void
    {
        foreach (['', 'not-an-email'] as $email) {
            $this->post($this->readersUrl(), ['email' => $email])->assertSessionHasErrors('email');
        }

        $this->assertSame(0, Reader::count());
    }

    public function test_a_reader_is_removed_from_their_guide_only(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC]);
        $mine = $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $theirs = $other->readers()->create(['email' => 'ana@gmail.com']);

        // Another guide's reader, through this guide's URL, is not found.
        $this->delete($this->readersUrl().'/'.$theirs->id)->assertNotFound();

        $this->delete($this->readersUrl().'/'.$mine->id)->assertRedirect();

        // Kept, marked removed: their guide then says it is no longer available to them.
        $this->assertSoftDeleted($mine);
        $this->get($this->readersUrl())->assertInertia(fn ($page) => $page->has('readers', 0));
        $this->assertModelExists($theirs);
    }

    public function test_the_other_languages_guide_is_not_this_editors(): void
    {
        $english = Guide::create(['locale' => 'en', 'title' => 'Excel for work', 'level' => Level::BASIC]);

        $this->get($this->readersUrl($english))->assertNotFound();
        $this->post($this->readersUrl($english), ['email' => 'ana@gmail.com'])->assertNotFound();
        $this->assertSame(0, Reader::count());
    }

    public function test_deleting_a_guide_deletes_its_readers(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->guide->delete();

        $this->assertModelMissing($reader);
    }

    public function test_only_arcadias_admin_reaches_the_screen(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['approved_at' => now()]);

        $this->actingAs($reader)->get($this->readersUrl())->assertForbidden();
        $this->actingAs($reader)->post($this->readersUrl(), ['email' => 'x@gmail.com'])->assertForbidden();
    }
}
