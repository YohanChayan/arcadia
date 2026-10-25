<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visitor asking for a guide, from the landing page's popup: what they want to learn, what
 * for, and how to answer them. Per locale, like everything on the guide hosts: each host's editor
 * reads its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('topic', 200);
            $table->string('purpose', 16)->nullable();
            // As typed, and what it is — derived on save, never sent by the form.
            $table->string('contact', 160);
            $table->string('contact_kind', 16);
            $table->string('name', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['locale', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_inquiries');
    }
};
