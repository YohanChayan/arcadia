<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reader's own notes on a guide — on one line (a video, a practice) or on a whole stage. Like a
 * tick, a note hangs off the account and the guide, and points at its line or stage by its lineage
 * key, never a version's id: a reader moved to a new version keeps every note on what survived.
 * Theirs alone; nobody else who reads the guide sees them, the owner included.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            // `item` or `stage`, and that node's key.
            $table->string('target_kind', 8);
            $table->string('target_key', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'guide_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_notes');
    }
};
