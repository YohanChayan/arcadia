<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reader's own answer to an activity that asks for no right one: a flip card they knew or not
 * yet, and later a practice they found easy or hard. On their account, by the target's key — a
 * card's own key, a line's lineage key — so it survives a new version as a tick or a note does.
 * One row per target; answering again replaces it, and `updated_at` says when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_activity_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            // `ActivityFamily`, and the key of what was answered within it.
            $table->string('family', 32);
            $table->string('target_key', 16);
            // `known` · `not_yet` for a card.
            $table->string('state', 16);
            $table->timestamps();

            $table->unique(['user_id', 'guide_id', 'family', 'target_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_activity_states');
    }
};
