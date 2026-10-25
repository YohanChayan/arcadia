<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request for access becomes an enrollment request (ADR 0022): it carries the requester's
 * optional message, and it is answered rather than deleted — approved or declined, by whom and
 * when — so a decline stands and the editor can count both. The owner's own note on it is his
 * record of the payment arranged outside Arcadia; the requester never sees it.
 *
 * Requests made before this are pending, as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_access_requests', function (Blueprint $table) {
            $table->string('status', 10)->default('pending')->after('user_id');
            $table->text('message')->nullable()->after('status');
            $table->text('owner_note')->nullable()->after('message');
            $table->timestamp('decided_at')->nullable()->after('owner_note');
            $table->foreignId('decided_by')->nullable()->after('decided_at')->constrained('users')->nullOnDelete();

            $table->index(['guide_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('guide_access_requests', function (Blueprint $table) {
            $table->dropIndex(['guide_id', 'status']);
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['status', 'message', 'owner_note', 'decided_at']);
        });
    }
};
