<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a guide open for enrollment is also shown in the catalogue (ADR 0024), or taken only by
 * its link. On by default, so every guide keeps what it does today — an open guide is listed — and
 * nothing has to be backfilled. It means nothing while the guide is closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->boolean('in_catalogue')->default(true)->after('open_for_enrollment');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('in_catalogue');
        });
    }
};
