<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visitor accepting a guide the assistant had already offered in that conversation — the
 * assistant's one measure of success. Once per conversation and guide; `turn_id` is the turn in
 * which they accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->foreignUlid('turn_id')->constrained('assistant_turns')->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'guide_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversions');
    }
};
