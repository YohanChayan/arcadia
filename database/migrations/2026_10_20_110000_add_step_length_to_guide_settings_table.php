<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long one step of a guide should take to watch, in minutes — what the research prompt asks
 * the AI for, as a range. The host's default; a guide's own comes from its prompt's values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('step_min_minutes')->default(4)->after('price_note');
            $table->unsignedTinyInteger('step_max_minutes')->default(8)->after('step_min_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('guide_settings', function (Blueprint $table) {
            $table->dropColumn(['step_min_minutes', 'step_max_minutes']);
        });
    }
};
