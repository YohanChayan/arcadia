<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A prompt version's declared variables — the blanks its `{{name}}` placeholders stand for. The
 * fill-in form is generated from them, in their order, and a request fills them by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_prompt_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('guide_prompt_versions')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('label', 160);
            $table->string('type', 16);
            $table->boolean('required')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['version_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_prompt_variables');
    }
};
