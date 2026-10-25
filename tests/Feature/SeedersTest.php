<?php

namespace Tests\Feature;

use App\Models\Guide;
use App\Models\User;
use App\Services\ArcadiaSite;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A fresh `migrate --seed` gives a working Arcadia: the owner is admitted and edits a guide, and a
 * seeded reader signs in and reads the guide assigned to them.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_the_owner_is_admitted_and_edits_a_guide(): void
    {
        $this->seedAll();
        $owner = User::firstWhere('email', 'yhndevside@gmail.com');

        $this->assertTrue(app(ArcadiaSite::class)->admits($owner));

        $this->actingAs($owner)->get('/es/editor/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('guides.total', 3));

        $guide = Guide::where('locale', 'es')->where('title', 'Excel para tu primer trabajo')->sole();
        $this->get("/es/editor/guides/{$guide->id}/content")->assertOk();
        $this->put("/es/editor/guides/{$guide->id}", [
            'title' => 'Excel para tu primera entrevista',
            'level' => $guide->level->value,
            'status' => $guide->status->value,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Excel para tu primera entrevista', $guide->fresh()->title);
    }

    public function test_a_seeded_reader_reads_the_guide_assigned_to_them(): void
    {
        $this->seedAll();
        $guide = Guide::where('title', 'Inglés para conversar en el trabajo')->sole();

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'password'])
            ->assertRedirect('/es/mis-guias');

        $this->get('/es/mis-guias')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('guides', 1));
        $this->get('/guia/'.$guide->link_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Guide'));
    }

    public function test_seeding_again_adds_no_second_owner(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(1, User::sole()->membership()->count());
    }

    public function test_the_fake_data_never_reaches_production(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` would first ask to confirm a production run.
        $this->expectException(RuntimeException::class);
        $this->app->make(DemoSeeder::class)->run();
    }
}
