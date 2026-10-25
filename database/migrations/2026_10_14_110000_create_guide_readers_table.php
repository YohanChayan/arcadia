<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a guide is for: one row per reader, many per guide.
 *
 * A reader is an email, assigned before the person has an account — they will sign in with the
 * Google account of that address. `user_id` is the account once one exists with that email.
 * A guide in each language is its own record, so a reader of both is assigned to both.
 *
 * Arcadia's first record ownership. See docs/architecture/arcadia.md § Readers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_readers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            // Lower-cased on the way in (`Reader::email()`), so one address is one reader.
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Who assigned them — the owner, for now; kept for when a second editor exists.
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['guide_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_readers');
    }
};
