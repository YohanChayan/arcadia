<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'yhndevside@gmail.com'], [
            'name' => 'Yohan Chayan',
            'password' => Hash::make('yhndev0YHN'),
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        // The admin role is a membership of Arcadia, not a column on the user.
        $user->membership()->firstOrCreate(
            [],
            ['role_id' => Role::where('alias_number', Role::ALIAS_ADMIN)->value('id')],
        );
    }
}
