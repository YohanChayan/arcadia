<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short note shown beside the price wherever the landing page states it — `BCV`, so a reader
 * in Venezuela reads the dollar figure at the central bank's rate. Optional; none shows nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_settings', function (Blueprint $table) {
            $table->string('price_note', 24)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('guide_settings', function (Blueprint $table) {
            $table->dropColumn('price_note');
        });
    }
};
