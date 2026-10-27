<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A section's activity (`ActivityFamily`): the flip cards of "must master". Written by the
 * additive import, copied into each new version with its section, frozen with a release; what of
 * it reaches a reader is `ActivityFamily::forPage()`'s to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_sections', function (Blueprint $table) {
            $table->json('activity')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('guide_sections', function (Blueprint $table) {
            $table->dropColumn('activity');
        });
    }
};
