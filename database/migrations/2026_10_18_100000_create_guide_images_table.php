<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guide's images, one per purpose (`ImageSlot`): the owner's upload, kept as it came, and the
 * crops cut from it, all in one directory on the `public` disk. On the guide, not a version: a
 * cover is presentation, as the title is, and changing it needs no release. A new upload gets a
 * new directory, so a cached preview of the old one is never served as the new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->string('slot', 16);
            // On the `public` disk: `guides/{guide}/{random}` (`StoreGuideImage`).
            $table->string('directory', 80);
            // The upload's own name in it: `original.png`.
            $table->string('filename', 16);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->timestamps();

            $table->unique(['guide_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_images');
    }
};
