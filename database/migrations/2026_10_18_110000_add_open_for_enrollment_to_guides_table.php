<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a guide takes enrollment requests from accounts it is not shared with (ADR 0022). Off
 * by default, so no guide starts taking them until the owner switches it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->boolean('open_for_enrollment')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('open_for_enrollment');
        });
    }
};
