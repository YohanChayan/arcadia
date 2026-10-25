<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_goal', function (Blueprint $table) {
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('guide_goals')->cascadeOnDelete();

            $table->primary(['guide_id', 'goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_goal');
    }
};
