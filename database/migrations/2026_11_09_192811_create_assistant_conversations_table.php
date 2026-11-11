<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One conversation with the sales assistant, on one channel and in one language. Keyed by a ULID,
 * since a public visitor holds its id with no account behind it. `user_id` is the signed-in
 * account that chatted — the owner testing in the editor today — and `participant` the channel's
 * own handle for an anonymous visitor later: a web session, a Telegram chat, a phone number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // `web` today; `telegram`, `whatsapp` later.
            $table->string('channel', 16);
            $table->string('locale', 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('participant', 64)->nullable();
            $table->timestamps();

            $table->index(['channel', 'participant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};
