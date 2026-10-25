<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a version changed, typed: the owner's record, and what decides whether an
        // earlier buyer is offered the upgrade. It describes the change; it owns no content.
        Schema::create('guide_version_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('guide_versions')->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_version_changes');
    }
};
