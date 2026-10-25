<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guide's colours: each reader's own, and the guide's default for new readers.
 *
 * An appearance is a `Palette` and an `Accent`, by key — never a colour, so a palette can be
 * retuned without touching rows. NULL is Arcadia's own (`arcadia` · `blue`).
 *
 * On the reader, `appearance_by` says who last saved it — `admin` or `reader` — and NULL means
 * nobody has since it was assigned; the editor's badge reads it. The guide's default is copied
 * into an assignment once, when it is made, and never inherited after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->string('default_palette', 32)->nullable()->after('status');
            $table->string('default_accent', 32)->nullable()->after('default_palette');
        });

        Schema::table('guide_readers', function (Blueprint $table) {
            $table->string('palette', 32)->nullable()->after('user_id');
            $table->string('accent', 32)->nullable()->after('palette');
            $table->string('appearance_by', 16)->nullable()->after('accent');
            $table->timestamp('appearance_at')->nullable()->after('appearance_by');
        });
    }

    public function down(): void
    {
        Schema::table('guide_readers', function (Blueprint $table) {
            $table->dropColumn(['palette', 'accent', 'appearance_by', 'appearance_at']);
        });

        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn(['default_palette', 'default_accent']);
        });
    }
};
