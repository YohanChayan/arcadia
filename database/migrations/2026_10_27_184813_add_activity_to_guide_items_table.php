<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line's activity (`ActivityFamily`): a quick check on a video, or a practice's hint, example
 * and easy/hard. Written by the additive import, copied into each new version with its line,
 * frozen with a release; what of it reaches a reader is `ActivityFamily::forPage()`'s to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->json('activity')->nullable()->after('brief');
        });
    }

    public function down(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->dropColumn('activity');
        });
    }
};
