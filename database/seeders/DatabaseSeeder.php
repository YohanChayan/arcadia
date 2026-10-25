<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A working Arcadia: its roles and Google, the owner, the per-language settings, the starter
 * phrases and prompts — and, locally only, fake guides and readers to work against.
 *
 * Every seeder here is idempotent except `DemoSeeder`, which is why it runs only locally: start
 * over with `php artisan migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AuthProviderSeeder::class,
            UserSeeder::class,
            GuideSettingsSeeder::class,
            GuidePhraseSeeder::class,
            PromptTemplateSeeder::class,
            CoverPromptTemplateSeeder::class,
            GuideFlyerPromptTemplateSeeder::class,
            ArcadiaFlyerPromptTemplateSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
