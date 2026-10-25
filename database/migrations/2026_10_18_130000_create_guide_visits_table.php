<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many people looked at a guide's presentation, by day (ADR 0022): a counter per guide and
 * day, and nothing else — no visitor, no IP, no browser. Counted on the server
 * (`GuideVisits`), so Arcadia keeps no analytics tool and no tracking cookie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('visits')->default(0);

            $table->unique(['guide_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_visits');
    }
};
