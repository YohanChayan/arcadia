<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a prompt version is for, or which approach it takes — so the owner can tell versions
 * apart where one is picked (a guide's Presentation screen) without opening each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_prompt_versions', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('guide_prompt_versions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
