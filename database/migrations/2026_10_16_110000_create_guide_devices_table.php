<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The devices an account reads Arcadia on (option D). A device is a browser: a long-lived random
 * value in a cookie on Arcadia's host, stored here only as its hash, with a label read from the
 * user agent ("Chrome · Android") and when it was first and last used. The `sessions` table
 * cannot be this — a session id changes at every sign-in and expires.
 *
 * Deleting a row is the revoke: the browser holding it is signed out on its next request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('label', 80);
            $table->timestamp('last_used_at');
            $table->timestamps();

            $table->index(['user_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_devices');
    }
};
