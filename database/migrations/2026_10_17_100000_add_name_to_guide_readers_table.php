<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the guide calls its reader: "María". Optional, set by the owner when adding them; without
 * one the guide uses the first name on their account, if they have one. The guide's greeting and
 * the email that shares it greet them by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->string('name', 80)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
