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
 * A guide's colours: a curated palette and accent per reader, a default for new readers copied
 * once, who last saved them, and the page painted in them — the public link, and the editor's
 * preview, live included.
 */
class GuideAppearanceTest extends TestCase
{
    use RefreshDatabase;

    /** Arcadia's own done colour, which no palette changes. */
    private const DONE = '#4ADE80';

    private Guide $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));

        $this->guide = Guide::create(['locale' => 'es', 'title' => 'Excel para el trabajo', 'level' => Level::BASIC]);
    }

    private function url(string $path = ''): string
    {
        return '/es/editor/guides/'.$this->guide->id.$path;
    }

    // ── The catalogue cannot make a guide unreadable ────────────────────────────────────────

    public function test_every_palette_keeps_its_text_readable(): void
    {
        foreach (Palette::cases() as $palette) {
            // Arcadia's own renders the site's tokens, not these; its colours are a reference.
            if ($palette === Palette::ARCADIA) {
                continue;
            }

            $c = $palette->colours();

            foreach (['ground', 'surface', 'well'] as $surface) {
                $this->assertGreaterThanOrEqual(7.0, $this->contrast($c['ink'], $c[$surface]), "{$palette->value}: ink on {$surface}");
                $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['ink_soft'], $c[$surface]), "{$palette->value}: soft ink on {$surface}");
                $this->assertGreaterThanOrEqual(3.0, $this->contrast(self::DONE, $c[$surface]), "{$palette->value}: done on {$surface}");
            }
        }
    }

    public function test_every_accent_reads_on_every_palette(): void
    {
        foreach (Palette::cases() as $palette) {
            foreach (Accent::cases() as $accent) {
                foreach (['ground', 'surface'] as $surface) {
                    // The accent is also small text — the category line — so the text floor, not 3:1.
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($accent->hex(), $palette->colours()[$surface]),
                        "{$accent->value} on {$palette->value} {$surface}");
                }
            }
        }

        foreach (Accent::cases() as $accent) {
            $this->assertGreaterThanOrEqual(4.5, $this->contrast(Accent::ON_ACCENT, $accent->hex()), "text on {$accent->value}");
        }
    }

    // ── Starting colours, and the badge ─────────────────────────────────────────────────────

    public function test_a_new_reader_starts_in_the_guides_default_and_with_no_badge(): void
    {
        $this->guide->update(['default_palette' => Palette::FOREST, 'default_accent' => Accent::AMBER]);

        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->assertSame(Palette::FOREST, $reader->palette);
        $this->assertSame(Accent::AMBER, $reader->accent);
        $this->assertNull($reader->appearance_by);
    }

    public function test_saving_the_default_repaints_no_reader_already_assigned(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->put($this->url('/appearance'), ['palette' => 'plum', 'accent' => 'pink'])->assertSessionHasNoErrors();

        $this->assertSame(Palette::PLUM, $this->guide->fresh()->default_palette);
        $this->assertNull($reader->fresh()->palette);
        $this->assertSame(Palette::PLUM, $this->guide->readers()->create(['email' => 'bob@gmail.com'])->palette);
    }

    public function test_the_owners_save_reads_me(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->put($this->url("/readers/{$reader->id}/appearance"), ['palette' => 'graphite', 'accent' => 'teal'])
            ->assertSessionHasNoErrors();

        $reader->refresh();
        $this->assertSame(Palette::GRAPHITE, $reader->palette);
        $this->assertSame(Accent::TEAL, $reader->accent);
        $this->assertSame(Reader::BY_ADMIN, $reader->appearance_by);
        $this->assertNotNull($reader->appearance_at);

        $this->get($this->url('/readers'))->assertInertia(fn ($page) => $page
            ->where('readers.0.palette', 'graphite')
            ->where('readers.0.accent', 'teal')
            ->where('readers.0.appearance_by', 'admin')
            ->has('appearanceOptions.palettes', count(Palette::cases()))
            ->has('appearanceOptions.accents', count(Accent::cases())));
    }

    public function test_the_owner_overwrites_a_readers_own_choice_only_when_confirmed(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $reader->paint(Palette::COCOA, Accent::CORAL, Reader::BY_READER);

        $this->put($this->url("/readers/{$reader->id}/appearance"), ['palette' => 'graphite', 'accent' => 'teal'])
            ->assertSessionHasErrors('overwrite');
        $this->assertSame(Palette::COCOA, $reader->fresh()->palette);

        $this->put($this->url("/readers/{$reader->id}/appearance"), ['palette' => 'graphite', 'accent' => 'teal', 'overwrite' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(Palette::GRAPHITE, $reader->fresh()->palette);
        $this->assertSame(Reader::BY_ADMIN, $reader->fresh()->appearance_by);
    }

    public function test_only_the_catalogues_colours_are_accepted(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);

        $this->put($this->url("/readers/{$reader->id}/appearance"), ['palette' => '#ff0000', 'accent' => 'teal'])
            ->assertSessionHasErrors('palette');
        $this->put($this->url('/appearance'), ['palette' => 'forest', 'accent' => 'neon'])
            ->assertSessionHasErrors('accent');
    }

    public function test_another_guides_reader_is_not_painted_through_this_one(): void
    {
        $other = Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC]);
        $theirs = $other->readers()->create(['email' => 'ana@gmail.com']);

        $this->put($this->url("/readers/{$theirs->id}/appearance"), ['palette' => 'forest', 'accent' => 'teal'])
            ->assertNotFound();
    }

    // ── The page, painted ────────────────────────────────────────────────────────────────────

    public function test_arcadias_own_colours_set_nothing(): void
    {
        $this->get($this->url('/preview'))->assertInertia(fn ($page) => $page->where('appearance', []));
    }

    public function test_the_preview_paints_a_reader_and_then_what_is_being_picked(): void
    {
        $reader = $this->guide->readers()->create(['email' => 'ana@gmail.com']);
        $reader->paint(Palette::FOREST, Accent::AMBER, Reader::BY_ADMIN);

        $this->get($this->url("/preview?reader={$reader->id}"))->assertInertia(fn ($page) => $page
            ->where('appearance.--guide-ground', Palette::FOREST->colours()['ground'])
            ->where('appearance.--guide-accent', Accent::AMBER->hex()));

        // Unsaved picks win, and are not saved.
        $this->get($this->url("/preview?reader={$reader->id}&palette=plum&accent=sky"))->assertInertia(fn ($page) => $page
            ->where('appearance.--guide-ground', Palette::PLUM->colours()['ground'])
            ->where('appearance.--guide-accent', Accent::SKY->hex()));
        $this->assertSame(Palette::FOREST, $reader->fresh()->palette);

        // Anything outside the catalogue is ignored, not rendered.
        $this->get($this->url('/preview?palette=%23ff0000'))->assertInertia(fn ($page) => $page->where('appearance', []));
    }

    public function test_the_link_opens_in_the_guides_default_until_readers_sign_in(): void
    {
        $this->guide->update(['status' => GuideStatus::PUBLISHED, 'default_palette' => Palette::GRAPHITE, 'default_accent' => Accent::VIOLET]);
        $this->guide->latestVersion()->release();

        $this->get('/guia/'.$this->guide->link_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('appearance.--guide-surface', Palette::GRAPHITE->colours()['surface'])
                ->where('appearance.--guide-accent', Accent::VIOLET->hex()));
    }

    /** WCAG 2 contrast ratio of two `#RRGGBB` colours. */
    private function contrast(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(fn ($pair) => hexdec($pair) / 255, str_split(ltrim($hex, '#'), 2));
            $linear = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

            return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        };

        [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }
}
