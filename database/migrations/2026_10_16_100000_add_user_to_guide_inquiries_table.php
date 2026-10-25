<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request for a guide made while signed in records the account: its email is the one to
 * assign the guide to. Nullable, because asking needs no account — a visitor signed out asks with
 * only a contact — and a deleted account leaves its request behind with that contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
