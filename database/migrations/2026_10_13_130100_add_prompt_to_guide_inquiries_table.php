<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The prompt the owner copied for a request: which version, and the values it was filled with,
 * so it can be copied again exactly and a guide made from the request can inherit it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->foreignId('prompt_version_id')->nullable()->after('name')
                ->constrained('guide_prompt_versions')->nullOnDelete();
            $table->json('prompt_values')->nullable()->after('prompt_version_id');
            $table->timestamp('prompted_at')->nullable()->after('prompt_values');
        });
    }

    public function down(): void
    {
        Schema::table('guide_inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prompt_version_id');
            $table->dropColumn(['prompt_values', 'prompted_at']);
        });
    }
};
