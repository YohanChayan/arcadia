<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who may do what on Arcadia, and how they sign in: the roles, a user's membership with its role,
 * and the Google links. The two roles are rows, not seeds — every membership points at one, so a
 * fresh database must have them before anyone registers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('alias_number')->nullable();
            $table->string('description')->nullable();
        });

        DB::table('roles')->insert([
            ['name' => 'Admin', 'description' => 'The owner, who edits the guides', 'alias_number' => Role::ALIAS_ADMIN],
            ['name' => 'Reader', 'description' => 'Someone a guide is assigned to', 'alias_number' => Role::ALIAS_READER],
        ]);

        Schema::create('site_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('site', 32);
            $table->foreignId('role_id')->constrained('roles');
            $table->timestamps();

            $table->unique(['user_id', 'site']);
        });

        Schema::create('auth_providers', function (Blueprint $table) {
            $table->id();
            $table->string('alias')->unique();
            $table->string('icon');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('user_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('auth_providers')->cascadeOnDelete();
            $table->string('provider_id_from_oauth');
            $table->text('token');
            $table->text('refresh_token')->nullable();
            $table->string('avatar')->nullable();
            $table->string('nickname')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'provider_id']);
            $table->index(['provider_id', 'provider_id_from_oauth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_auth_providers');
        Schema::dropIfExists('auth_providers');
        Schema::dropIfExists('site_memberships');
        Schema::dropIfExists('roles');
    }
};
