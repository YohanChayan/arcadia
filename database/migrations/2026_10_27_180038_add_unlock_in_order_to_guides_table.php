<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a reader opens a guide's stages one after another: each stage locked until the one
 * before it is complete. The guide's own setting, not a version's — the lock itself is derived
 * in the page from the stage order and the reader's ticks, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->boolean('unlock_in_order')->default(true)->after('open_for_enrollment');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('unlock_in_order');
        });
    }
};
