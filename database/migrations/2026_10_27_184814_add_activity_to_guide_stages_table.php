<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stage's activity (`ActivityFamily`): its mini-game. Written by the additive import, copied
 * into each new version with its stage, frozen with a release; what of it reaches a reader is
 * `ActivityFamily::forPage()`'s to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_stages', function (Blueprint $table) {
            $table->json('activity')->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('guide_stages', function (Blueprint $table) {
            $table->dropColumn('activity');
        });
    }
};
