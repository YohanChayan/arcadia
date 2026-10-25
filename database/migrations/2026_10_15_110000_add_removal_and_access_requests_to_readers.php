<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guides behind sign-in need two things the readers table did not hold.
 *
 * - **A removed reader is kept, marked removed** (`deleted_at`), so their guide can tell them
 *   "no longer available to you" rather than "not shared with this account" — the two read the
 *   same without it. Adding the same email back restores the row, colours and all.
 * - **A request for access**: a signed-in account opening a guide not shared with it may ask,
 *   once per guide; the owner accepts it on the guide's Readers screen, which assigns them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('guide_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guide_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_access_requests');

        Schema::table('guide_readers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
