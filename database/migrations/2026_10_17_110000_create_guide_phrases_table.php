<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short motivation phrases shown to a reader — one line, read in a glance — each for a moment
 * (`PhraseMoment`): arriving at their guides, a guide not started, under way, nearly done, done.
 * Per language, like everything on Arcadia. General by design: any guide may draw on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_phrases', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('moment', 12);
            $table->string('text', 120);
            $table->timestamps();

            $table->index(['locale', 'moment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_phrases');
    }
};
