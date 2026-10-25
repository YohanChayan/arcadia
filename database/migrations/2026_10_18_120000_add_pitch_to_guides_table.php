<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guide's pitch: a few lines the owner writes to present it to someone who is not yet its
 * reader, on the page its link shows them (ADR 0022). Not the greeting, which is written to a
 * reader by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->text('pitch')->nullable()->after('greeting');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('pitch');
        });
    }
};
