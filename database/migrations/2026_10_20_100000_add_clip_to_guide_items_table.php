<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The part of a YouTube video a line plays — from `clip_start` to `clip_end`, in seconds from the
 * start of the video — and the evidence it was taken from: a chapter in the video's description,
 * or a line of its transcript. A research AI cannot watch a video, so a clip without evidence is
 * not stored.
 *
 * Unlike the brief, the clip belongs to the video, not the lesson: "fix everywhere" clears it
 * when it swaps the link. A new version copies it with the line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->unsignedInteger('clip_start')->nullable()->after('brief');
            $table->unsignedInteger('clip_end')->nullable()->after('clip_start');
            $table->text('clip_evidence')->nullable()->after('clip_end');
        });
    }

    public function down(): void
    {
        Schema::table('guide_items', function (Blueprint $table) {
            $table->dropColumn(['clip_start', 'clip_end', 'clip_evidence']);
        });
    }
};
