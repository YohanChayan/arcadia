<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account holds one membership: this application is Arcadia alone, so the `site` every row
 * carried says nothing. It fails on an account with two rows — a membership of another site —
 * rather than choose which to keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_memberships', function (Blueprint $table) {
            // First, so `user_id`'s foreign key keeps an index while the pair's is dropped.
            $table->unique('user_id');
            $table->dropUnique(['user_id', 'site']);
            $table->dropColumn('site');
        });
    }

    public function down(): void
    {
        Schema::table('site_memberships', function (Blueprint $table) {
            $table->string('site', 32)->default('arcadia')->after('user_id');
            $table->unique(['user_id', 'site']);
            $table->dropUnique(['user_id']);
        });
    }
};
