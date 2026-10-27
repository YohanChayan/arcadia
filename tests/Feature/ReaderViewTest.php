<?php

namespace Tests\Feature;

use App\Enums\Accent;
use App\Enums\CardKind;
use App\Enums\GuideStatus;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\Palette;
use App\Models\Guide;
use App\Models\Item;
use App\Models\Note;
use App\Models\Reader;
use App\Models\Role;
use App\Models\Tick;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner's view of a guide as one reader sees it: their ticks on the released version, in their
 * colours, read only — nothing on it can change anything of theirs — and of their notes only where
 * there are some, never what they say.
 */
class ReaderViewTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Item $first;

    private User $ana;

    private Reader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        $stage = $this->guide->latestVersion()->stages()->create(['title' => 'Fórmulas', 'position' => 1]);
        $card = $stage->cards()->create(['kind' => CardKind::SOURCE, 'position' => 1]);
        $this->first = $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Una', 'body' => 'x', 'position' => 1]);
        $card->items()->create(['kind' => ItemKind::PRACTICE, 'title' => 'Otra', 'body' => 'x', 'position' => 2]);
        $this->guide->latestVersion()->release();

        $this->ana = User::factory()->withRole(Role::ALIAS_READER)->create(['name' => 'Ana Pérez']);
        $this->reader = $this->guide->readers()->create(['email' => $this->ana->email, 'user_id' => $this->ana->id]);
        $this->reader->paint(Palette::FOREST, Accent::AMBER, Reader::BY_READER);
        $this->reader->tick($this->first->key, true);
        Note::create(['user_id' => $this->ana->id, 'guide_id' => $this->guide->id, 'target_kind' => Note::STAGE, 'target_key' => $stage->key, 'body' => 'Mi secreto']);
    }

    private function asOwner(): static
    {
        return $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]));
    }

    private function url(?Reader $reader = null, ?Guide $guide = null): string
    {
        return '/es/editor/guides/'.($guide ?? $this->guide)->id.'/readers/'.($reader ?? $this->reader)->id.'/view';
    }

    public function test_the_owner_sees_the_guide_as_the_reader_does_with_their_ticks_and_nothing_to_change(): void
    {
        $response = $this->asOwner()->get($this->url())->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Guide')
            ->where('viewingAs', 'Ana')
            ->where('readerName', 'Ana')
            ->where('progress', ['ticked' => [$this->first->key], 'unlockInOrder' => true, 'readOnly' => true])
            ->where('noteMarks', ['stage:'.$this->guide->latestVersion()->stages()->sole()->key])
            // Nowhere to save a tick, answer, note or colour; no phrase, which would spend theirs.
            ->missing('notes')
            ->missing('readerAppearance')
            ->missing('motivation')
            ->missing('celebration'));

        // Their colours, and never what their notes say.
        $this->assertEquals($this->reader->fresh()->appearanceTokens(), $response->viewData('page')['props']['appearance']);
        $this->assertStringNotContainsString('Mi secreto', $response->getContent());
    }

    public function test_viewing_changes_nothing_of_theirs(): void
    {
        $this->asOwner()->get($this->url())->assertOk();

        $this->assertSame([$this->first->key], $this->reader->fresh()->tickedKeys());
        $this->assertSame(1, Tick::count());
        $this->assertSame('Mi secreto', Note::query()->sole()->body);
        $this->assertSame(Palette::FOREST, $this->reader->fresh()->palette);
    }

    public function test_only_a_reader_of_this_guide_with_an_account_once_it_is_released(): void
    {
        $this->asOwner();

        $other = Guide::create(['locale' => 'es', 'title' => 'Word', 'level' => Level::BASIC]);
        $this->get($this->url(guide: $other))->assertNotFound();

        $noAccount = $this->guide->readers()->create(['email' => 'luis@gmail.com']);
        $this->get($this->url($noAccount))->assertNotFound();

        $english = Guide::create(['locale' => 'en', 'title' => 'Excel', 'level' => Level::BASIC]);
        $this->get('/es/editor/guides/'.$english->id.'/readers/'.$this->reader->id.'/view')->assertNotFound();

        $removed = User::factory()->withRole(Role::ALIAS_READER)->create();
        $gone = $this->guide->readers()->create(['email' => $removed->email, 'user_id' => $removed->id]);
        $gone->delete();
        $this->get($this->url($gone))->assertNotFound();

        $unreleased = Guide::create(['locale' => 'es', 'title' => 'Draft', 'level' => Level::BASIC]);
        $theirs = $unreleased->readers()->create(['email' => $this->ana->email, 'user_id' => $this->ana->id]);
        $this->get($this->url($theirs, $unreleased))->assertNotFound();
    }

    public function test_a_reader_gets_no_view_of_anyone(): void
    {
        $this->actingAs($this->ana)->get($this->url())->assertForbidden();
    }

    public function test_the_privacy_page_says_the_author_sees_the_ticks(): void
    {
        $this->get('/privacidad')->assertOk();

        $this->assertStringContainsString('Quien prepara la guía ve qué líneas marcaste', __('arcadia.privacy.sections.progress.body', [], 'es'));
        $this->assertStringContainsString("The guide's author sees which lines you ticked", __('arcadia.privacy.sections.progress.body', [], 'en'));
    }
}
