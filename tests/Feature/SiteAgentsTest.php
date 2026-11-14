<?php

namespace Tests\Feature;

use App\Assistant\Models\Conversation;
use App\Assistant\Models\Conversion;
use App\Assistant\Models\Turn;
use App\Assistant\TurnStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Site settings → Agents: the sales assistant's activity and the knowledge index, as counts and
 * metrics — never what anyone said — read site-wide in either editor.
 */
class SiteAgentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        config(['assistant.provider' => 'gemini', 'ai.providers.gemini.key' => 'test-key']);

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    private function turn(Conversation $conversation, TurnStatus $status, string $at, array $attributes = []): Turn
    {
        $turn = $conversation->turns()->create([
            'status' => $status,
            'visitor_message' => 'A secret question',
            'reply' => 'A secret answer',
            ...$attributes,
        ]);
        $turn->forceFill(['created_at' => $at, 'updated_at' => $attributes['updated_at'] ?? $at])->saveQuietly();

        return $turn;
    }

    private function usage(int $input, int $cached, int $output): array
    {
        $call = ['input_tokens' => $input, 'output_tokens' => $output, 'cache_read_input_tokens' => $cached];

        return ['planner' => $call, 'speaker' => $call];
    }

    public function test_the_assistant_is_counted_in_the_window_without_any_of_its_text(): void
    {
        $web = Conversation::create(['channel' => 'web', 'locale' => 'es']);
        $console = Conversation::create(['channel' => 'console', 'locale' => 'es']);
        $old = Conversation::create(['channel' => 'web', 'locale' => 'es']);
        $old->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        $done = $this->turn($web, TurnStatus::DONE, now()->subHour(), [
            'model' => 'gemini-3.1-flash-lite', 'engagement' => 40, 'cost' => 0.0015,
            'usage' => $this->usage(100, 20, 10), 'updated_at' => now()->subHour()->addSeconds(4),
        ]);
        $this->turn($console, TurnStatus::DONE, now()->subMinutes(30), [
            'model' => 'gemini-3.1-flash-lite', 'engagement' => 60,
            'usage' => $this->usage(200, 0, 30), 'updated_at' => now()->subMinutes(30)->addSeconds(6),
        ]);
        $this->turn($web, TurnStatus::FAILED, now()->subMinutes(10));
        $this->turn($old, TurnStatus::DONE, now()->subDays(3), ['usage' => $this->usage(999, 0, 999)]);

        Conversion::create([
            'conversation_id' => $web->id,
            'turn_id' => $done->id,
            'guide_id' => Guide::create(['locale' => 'es', 'title' => 'Excel desde cero', 'level' => Level::BASIC])->id,
        ]);

        $response = $this->get('/es/editor/settings/agents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('editor/settings/Agents')
                ->where('filter.range', '24h')
                ->where('assistant.provider', 'gemini')
                ->where('assistant.key_present', true)
                ->where('assistant.conversations.total', 2)
                ->where('assistant.conversations.by_channel', ['console' => 1, 'web' => 1])
                ->where('assistant.turns.total', 3)
                ->where('assistant.turns.done', 2)
                ->where('assistant.turns.failed', 1)
                ->where('assistant.by_model', ['gemini-3.1-flash-lite' => 2])
                // Both calls of each turn: (100 + 100) + (200 + 200) input, (10 + 10) + (30 + 30) output.
                ->where('assistant.usage.input_tokens', 600)
                ->where('assistant.usage.cached_input_tokens', 40)
                ->where('assistant.usage.output_tokens', 80)
                ->where('assistant.usage.average_seconds', 5)
                ->where('assistant.cost', 0.0015)
                ->where('assistant.engagement', 50)
                ->where('assistant.conversions', 1)
                // A failed turn is a warning, not an outage.
                ->where('assistant.level', 'warn'));

        $this->assertStringNotContainsString('secret', $response->getContent());

        $this->get('/en/editor/settings/agents?agents_range=7d')
            ->assertInertia(fn ($page) => $page->where('assistant.conversations.total', 3)->where('assistant.turns.total', 4));

        $this->get('/es/editor/settings/agents?agents_range=never')->assertSessionHasErrors('agents_range');
    }

    public function test_cost_is_unknown_not_zero_while_no_turn_is_priced(): void
    {
        $this->turn(Conversation::create(['channel' => 'web', 'locale' => 'es']), TurnStatus::DONE, now()->subHour());

        $this->get('/es/editor/settings/agents')
            ->assertInertia(fn ($page) => $page->where('assistant.cost', null)->where('assistant.level', 'ok'));
    }

    public function test_a_turn_left_replying_is_stuck_whenever_it_started(): void
    {
        $conversation = Conversation::create(['channel' => 'web', 'locale' => 'es']);
        $this->turn($conversation, TurnStatus::REPLYING, now()->subDays(2));
        $this->turn($conversation, TurnStatus::REPLYING, now()->subMinute());

        $this->get('/es/editor/settings/agents')
            ->assertInertia(fn ($page) => $page
                ->where('assistant.turns.replying', 1)
                ->where('assistant.turns.stuck', 1)
                ->where('assistant.level', 'warn'));
    }

    public function test_a_provider_with_no_key_is_an_error(): void
    {
        config(['ai.providers.gemini.key' => null]);

        $this->get('/es/editor/settings/agents')
            ->assertInertia(fn ($page) => $page->where('assistant.key_present', false)->where('assistant.level', 'error'));
    }

    public function test_the_index_queue_counts_its_own_jobs_only(): void
    {
        foreach (['knowledge', 'knowledge', 'arcadia'] as $queue) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()]);
        }
        DB::table('failed_jobs')->insert([
            'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'connection' => 'database', 'queue' => 'knowledge',
            'payload' => '{}', 'exception' => 'Embedding failed', 'failed_at' => now(),
        ]);

        $this->get('/es/editor/settings/agents')
            ->assertInertia(fn ($page) => $page
                ->where('knowledge.queue', 'knowledge')
                ->where('knowledge.pending', 2)
                ->where('knowledge.failed', 1)
                ->where('knowledge.embedding_model', config('knowledge.embeddings.model'))
                ->where('knowledge.level', 'error'));
    }
}
