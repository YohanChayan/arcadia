<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A slot may hold several images — a guide's flyers — so guide and slot are no longer unique.
 * How many a slot holds is `ImageSlot::maxImages()`'s, and a one-image slot is replaced in place
 * by `StoreGuideImage`. The plain index is added first: the guide's foreign key leans on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_images', function (Blueprint $table) {
            $table->index(['guide_id', 'slot']);
            $table->dropUnique(['guide_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::table('guide_images', function (Blueprint $table) {
            $table->unique(['guide_id', 'slot']);
            $table->dropIndex(['guide_id', 'slot']);
        });
    }
};
