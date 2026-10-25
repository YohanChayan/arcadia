<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a guide came from: the request it answers, and the prompt version and values that
 * researched it — inherited from that request. Empty for a guide written by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->nullable()->after('counterpart_id')
                ->constrained('guide_inquiries')->nullOnDelete();
            $table->foreignId('prompt_version_id')->nullable()->after('inquiry_id')
                ->constrained('guide_prompt_versions')->nullOnDelete();
            $table->json('prompt_values')->nullable()->after('prompt_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inquiry_id');
            $table->dropConstrainedForeignId('prompt_version_id');
            $table->dropColumn('prompt_values');
        });
    }
};
