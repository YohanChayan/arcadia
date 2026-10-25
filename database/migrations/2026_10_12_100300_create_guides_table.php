<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learning guides' root record.
 *
 * A guide is versions, and each version is a full copy: prose sections, and stage → card →
 * item. A later version may add, modify, remove or reorder anything, because content goes
 * stale — a video is taken down, a site dies — in the base and in later additions alike.
 *
 * Every guides table is `guide_`-prefixed. See docs/architecture.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('title');
            $table->string('level');
            $table->foreignId('category_id')->nullable()->constrained('guide_categories')->nullOnDelete();
            // The same guide in the other language — a separate record, never a translation.
            $table->foreignId('counterpart_id')->nullable()->constrained('guides')->nullOnDelete();
            // The only way to a guide: there is no catalogue. Rotatable, never the id.
            $table->string('link_token', 32)->unique();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
