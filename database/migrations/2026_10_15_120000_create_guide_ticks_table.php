<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reader's progress, on their account: one row per line they ticked.
 *
 * Against the item's `key`, with **no version**, because the key is the line's identity across
 * versions — a reader moved to a new version keeps every tick on a line that survived. Against
 * the account and the guide, not the assignment, so a reader removed and added back finds their
 * ticks where they left them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_ticks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->string('item_key', 16);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'guide_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_ticks');
    }
};
