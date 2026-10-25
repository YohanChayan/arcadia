<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * One settings row per guide host's language, at the default price. Never overwrites a price the
 * owner has set: an existing row is left as it is.
 */
class GuideSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['es', 'en'] as $locale) {
            Setting::firstOrCreate(['locale' => $locale], ['price' => Setting::DEFAULT_PRICE]);
        }
    }
}
