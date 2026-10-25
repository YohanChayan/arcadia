<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a buyer wants out of it, in the owner's words: "Excel to get hired in retail".
        Schema::create('guide_goals', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_goals');
    }
};
