<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A guide's goals become its own lines of text (ADR 0020). They were a registered vocabulary per
 * language, meant to map a buyer's words onto shared goals; in practice each one was the outcome
 * of one guide ("Cerrar ventas por WhatsApp"), had to be registered before a guide could carry it,
 * and an AI's goal not registered was dropped on import. What is shared across guides is what
 * categories and topics are for.
 *
 * Each guide keeps the wording it had, in the order the goals were registered. A prompt variable
 * that chose from the goals becomes plain text, its filled values unchanged. Then the vocabulary
 * goes. `down()` brings the tables and the guides' links back, the goals as new terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->json('goals')->nullable()->after('level');
        });

        DB::table('guide_goal')
            ->join('guide_goals', 'guide_goals.id', '=', 'guide_goal.goal_id')
            ->orderBy('guide_goals.id')
            ->get(['guide_goal.guide_id', 'guide_goals.name'])
            ->groupBy('guide_id')
            ->each(fn ($rows, $guideId) => DB::table('guides')->where('id', $guideId)
                ->update(['goals' => json_encode($rows->pluck('name')->values()->all(), JSON_UNESCAPED_UNICODE)]));

        DB::table('guide_prompt_variables')->where('type', 'goal')->update(['type' => 'text']);

        Schema::dropIfExists('guide_goal');
        Schema::dropIfExists('guide_goals');
    }

    public function down(): void
    {
        Schema::create('guide_goals', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'name']);
        });

        Schema::create('guide_goal', function (Blueprint $table) {
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('guide_goals')->cascadeOnDelete();

            $table->primary(['guide_id', 'goal_id']);
        });

        DB::table('guides')->whereNotNull('goals')->get(['id', 'locale', 'goals'])->each(function ($guide) {
            foreach (json_decode($guide->goals, true) ?? [] as $name) {
                $goal = DB::table('guide_goals')->where('locale', $guide->locale)->where('name', $name)->value('id')
                    ?? DB::table('guide_goals')->insertGetId(['locale' => $guide->locale, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('guide_goal')->insertOrIgnore(['guide_id' => $guide->id, 'goal_id' => $goal]);
            }
        });

        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('goals');
        });
    }
};
