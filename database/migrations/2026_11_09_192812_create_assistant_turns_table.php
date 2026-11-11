<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One visitor message and the assistant's reply to it, with what the turn decided and cost: the
 * engagement score, the guide it offered, the passages it was given (a snapshot — the index is
 * rebuilt on release) and the usage of its two model calls, the planner's and the speaker's. A
 * failed turn keeps its row and is left out of the history the model is shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_turns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            // `TurnStatus`: replying · done · failed.
            $table->string('status', 16);
            $table->text('visitor_message');
            $table->text('reply')->nullable();
            // 0–100, the planner's reading of the visitor after this message.
            $table->unsignedTinyInteger('engagement')->nullable();
            // The guide the reply was told to offer; no foreign key, so a deleted guide keeps the record.
            $table->unsignedBigInteger('offered_guide_id')->nullable();
            $table->json('passages')->nullable();
            // `{planner: {...}, speaker: {...}}`, each a `TextUsage` array.
            $table->json('usage')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->string('model', 64)->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_turns');
    }
};
