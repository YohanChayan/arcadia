<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guide taxonomy: one record per locale — not the JSON-per-locale columns `components` uses —
 * because a Spanish and an English guide are separate products with separate vocabularies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_categories', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_categories');
    }
};
