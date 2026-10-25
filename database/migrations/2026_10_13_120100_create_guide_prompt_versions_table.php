<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A prompt template's versions. A version is frozen once a guide has been produced from it
 * (`frozen_at`), so a guide can always say exactly which prompt made it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('guide_prompt_templates')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_prompt_versions');
    }
};
