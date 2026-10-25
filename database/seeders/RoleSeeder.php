<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Arcadia's two roles. The accounts migration inserts them already; this keeps a database that
 * lost one whole, and is idempotent.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['alias_number' => Role::ALIAS_ADMIN],
            ['name' => 'Admin', 'description' => 'The owner, who edits the guides'],
        );

        Role::firstOrCreate(
            ['alias_number' => Role::ALIAS_READER],
            ['name' => 'Reader', 'description' => 'Someone a guide is assigned to'],
        );
    }
}
