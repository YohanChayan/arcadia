<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('guide_versions')->cascadeOnDelete();
            // The node's identity ACROSS versions, as an item's key is: minted by the app, copied
            // unchanged into every later version, never edited. What a version comparison matches
            // on, so a renamed stage reads as renamed, not as removed and added.
            $table->string('key', 16);
            $table->string('title');
            // "When you finish…"
            $table->string('goal')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['version_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_stages');
    }
};
