<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every version is a full copy of the guide; version 1 is the base. A released version
        // is frozen — only fix-everywhere may touch it — so a buyer keeps what they bought.
        Schema::create('guide_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['guide_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_versions');
    }
};
