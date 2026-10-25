<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lines a reader ticks off. An item's `key` is shared by its copies across versions, so
 * progress follows a reader to a new version, and a dead item is repaired in every version
 * at once, by key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('guide_cards')->cascadeOnDelete();
            // Denormalised from card → stage, so a key can be unique within its version.
            $table->foreignId('version_id')->constrained('guide_versions')->cascadeOnDelete();
            // The identity of this line ACROSS versions: minted by the app, copied unchanged into
            // every later version, never edited. A reader's progress is stored on their device
            // against it, so a new key silently clears their checkmark.
            $table->string('key', 16);
            $table->string('kind');
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->string('hint')->nullable();
            $table->text('body')->nullable();
            // Stored and edited now; enforced by the paywall, which is a later pack.
            $table->boolean('is_preview')->default(false);
            // False once the source has gone — a removed video, a dead site — so the editor
            // shows what needs replacing, everywhere at once.
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['version_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_items');
    }
};
