<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request's read/unread marker, gone: where it stands is read from its prompt and its guide
 * (`InquiryStage`), which say whether it was answered rather than whether it was seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
        });
    }
};
