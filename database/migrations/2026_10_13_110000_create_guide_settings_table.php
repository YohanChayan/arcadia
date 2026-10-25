<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the owner sets for each guide host, one row per locale. Today only the price a guide is
 * offered at on the landing page, in US dollars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_settings', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2)->unique();
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_settings');
    }
};
