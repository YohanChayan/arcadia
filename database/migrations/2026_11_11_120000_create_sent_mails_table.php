<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email that left the site, for Site settings → Mail: when, to whom, its subject and
 * which mail it was. Never its body. Written by `RecordSentMail` on the framework's `MessageSent`,
 * so every sender is covered without touching any of them, and kept 30 days — older rows are
 * pruned as new ones are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_mails', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sent_at')->index();
            // Every `To` address, comma-separated: arcadia sends to one, but a message may carry more.
            $table->string('recipient', 500);
            $table->string('subject', 500)->nullable();
            // The Mailable or Notification class; null for a raw send, as the Health tab's test.
            $table->string('mailable')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_mails');
    }
};
