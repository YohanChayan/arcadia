<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The knowledge index, on the `vectors` connection (Postgres + pgvector). It lives in its own
 * folder, migrated with `--database=vectors --path=database/migrations/vectors`, so MySQL's
 * migrations — and the tests' in-memory SQLite — never reach it.
 *
 * Each row is one promise-level passage and its vector. `identity` names the passage across
 * versions; `content_hash` lets a rebuild skip what has not changed. 768 dimensions: changing them
 * is a new column and a full rebuild.
 */
return new class extends Migration
{
    protected $connection = 'vectors';

    public function up(): void
    {
        Schema::connection('vectors')->ensureVectorExtensionExists();

        Schema::connection('vectors')->create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('identity')->unique();
            $table->string('source', 40);
            $table->unsignedBigInteger('guide_id')->index();
            $table->string('guide_title');
            $table->unsignedBigInteger('version_id');
            $table->string('locale', 2);
            $table->string('chunk_type', 20);
            $table->string('stage_key', 12)->nullable();
            $table->unsignedSmallInteger('stage_position')->nullable();
            $table->boolean('listed');
            $table->text('content');
            $table->char('content_hash', 64);
            $table->string('embedding_model', 80);
            $table->vector('embedding', 768)->index();
            $table->timestamps();

            $table->index(['locale', 'listed']);
        });
    }

    public function down(): void
    {
        Schema::connection('vectors')->dropIfExists('knowledge_chunks');
    }
};
