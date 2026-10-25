<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a reader is told by email that a guide is theirs, and when they were. `notify` is the
 * owner's choice when adding them; `notified_at` is set once the email has gone. A reader to be
 * told whose guide cannot be opened yet waits — `notify` and no `notified_at` — and is told when
 * it can.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('assigned_by');
            $table->timestamp('notified_at')->nullable()->after('notify');
        });
    }

    public function down(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->dropColumn(['notify', 'notified_at']);
        });
    }
};
