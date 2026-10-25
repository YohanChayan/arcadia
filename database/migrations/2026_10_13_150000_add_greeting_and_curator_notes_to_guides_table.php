<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two parts of a researched guide that live on the guide, not in a version: the personal
 * greeting its reader sees first, and the curator's notes — what the research discarded and
 * could not verify — which only the owner sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->text('greeting')->nullable()->after('status');
            $table->json('curator_notes')->nullable()->after('prompt_values');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn(['greeting', 'curator_notes']);
        });
    }
};
