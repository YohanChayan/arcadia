<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AI research prompt the owner keeps, fills and copies to research a guide. One per routine,
 * not per language: the language is one of its variables (option PT3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_prompt_templates');
    }
};
