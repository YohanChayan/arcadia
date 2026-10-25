<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a linked line teaches, for the owner alone: a short summary, the topics it covers, its
 * level, creator, length and language, and the words to search for another — written by the
 * research AI with the link. When the video or site is gone, it is what a replacement must match.
 *
 * It describes the lesson, not the one video: "fix everywhere" swaps the link and keeps it, and a
 * new version copies it with the line. Never sent to a reader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->json('brief')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->dropColumn('brief');
        });
    }
};
