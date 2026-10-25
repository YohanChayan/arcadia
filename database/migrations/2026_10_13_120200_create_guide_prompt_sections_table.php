<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A prompt version's ordered, typed sections — persona, brief, research routine and the rest,
 * the output contract last. Their bodies carry `{{variable}}` placeholders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_prompt_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('guide_prompt_versions')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedInteger('position');
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['version_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_prompt_sections');
    }
};
