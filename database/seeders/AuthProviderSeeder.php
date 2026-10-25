<?php

namespace Database\Seeders;

use App\Models\AuthProvider;
use Illuminate\Database\Seeder;

/**
 * The social sign-ins Arcadia offers: Google only. `AdmitGoogleAccount` needs this row.
 */
class AuthProviderSeeder extends Seeder
{
    public function run(): void
    {
        AuthProvider::firstOrCreate([
            'alias' => 'google',
            'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg',
        ]);
    }
}
