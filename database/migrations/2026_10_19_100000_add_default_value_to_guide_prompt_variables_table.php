<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a variable's field starts filled with — the site's address, a headline the owner keeps —
 * so a value that rarely changes is not typed on every fill. Part of the version, frozen with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_prompt_variables', function (Blueprint $table) {
            $table->text('default_value')->nullable()->after('required');
        });
    }

    public function down(): void
    {
        Schema::table('guide_prompt_variables', function (Blueprint $table) {
            $table->dropColumn('default_value');
        });
    }
};
