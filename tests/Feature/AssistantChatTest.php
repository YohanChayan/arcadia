<?php

namespace Tests\Feature;

use App\Assistant\Agents\Planner;
use App\Assistant\Agents\Speaker;
use App\Assistant\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The editor's test chat posts one message and reads the reply back as server-sent events —
 * `turn`, `delta` as it is written, `done` with the turn's metrics — over a conversation only
 * the account that started it can continue. Only the owner reaches it.
 */
class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assistant.cache_instructions' => false]);

        $this->owner = $this->admin();
    }

    public function test_the_test_screen_says_which_models_answer_and_whether_there_is_a_key(): void
    {
        config(['assistant.provider' => 'gemini', 'ai.providers.gemini.key' => null]);

        $this->actingAs($this->owner)
            ->get(route('es.editor.assistant.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('editor/assistant/Index')
                ->where('models', config('assistant.models'))
                ->where('hasKey', false)
                ->where('maxLength', 2000));

        config(['ai.providers.gemini.key' => 'a-key']);

        $this->get(route('es.editor.assistant.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hasKey', true));
    }

    public function test_a_message_is_answered_as_a_stream_of_events(): void
    {
        Planner::fake([['engagement' => 61, 'accepted_guide_id' => null, 'offer_guide_id' => null]]);
        Speaker::fake(['Hola, ¿qué quieres aprender?']);

        $response = $this->actingAs($this->owner)->postJson(route('es.editor.assistant.messages'), ['message' => '  Hola  ']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')
            ->assertHeader('X-Accel-Buffering', 'no');

        $events = $this->events($response);
        $conversation = Conversation::sole();

        $this->assertSame('turn', $events[0]['event']);
        $this->assertSame($conversation->id, $events[0]['data']['conversation_id']);
        $this->assertSame('Hola, ¿qué quieres aprender?', collect($events)->where('event', 'delta')->pluck('data.text')->implode(''));
        $this->assertSame('done', last($events)['event']);
        $this->assertSame(61, last($events)['data']['engagement']);

        $this->assertSame(['web', 'es', $this->owner->id], [$conversation->channel, $conversation->locale, $conversation->user_id]);
        $this->assertSame('Hola', $conversation->turns()->sole()->visitor_message);
    }

    public function test_a_conversation_is_continued_by_its_id(): void
    {
        Planner::fake([
            ['engagement' => 50, 'accepted_guide_id' => null, 'offer_guide_id' => null],
            ['engagement' => 55, 'accepted_guide_id' => null, 'offer_guide_id' => null],
        ]);
        Speaker::fake(['Uno', 'Dos']);

        $first = $this->events($this->actingAs($this->owner)->postJson(route('es.editor.assistant.messages'), ['message' => 'Hola']));
        $conversationId = $first[0]['data']['conversation_id'];

        $this->events($this->postJson(route('es.editor.assistant.messages'), ['message' => 'Excel', 'conversation_id' => $conversationId]));

        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Conversation::sole()->turns()->count());
    }

    public function test_another_accounts_conversation_cannot_be_continued(): void
    {
        $theirs = Conversation::create(['channel' => 'web', 'locale' => 'es', 'user_id' => $this->admin()->id]);

        $this->actingAs($this->owner)
            ->postJson(route('es.editor.assistant.messages'), ['message' => 'Hola', 'conversation_id' => $theirs->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation_id');

        Planner::assertNeverPrompted();
    }

    public function test_an_empty_or_overlong_message_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('es.editor.assistant.messages'), ['message' => '   '])
            ->assertJsonValidationErrors('message');

        $this->postJson(route('es.editor.assistant.messages'), ['message' => str_repeat('a', 2001)])
            ->assertJsonValidationErrors('message');
    }

    public function test_only_the_owner_reaches_the_test_chat(): void
    {
        $reader = User::factory()->withRole(Role::ALIAS_READER)->create(['approved_at' => now(), 'email_verified_at' => now()]);

        $this->actingAs($reader)
            ->postJson(route('es.editor.assistant.messages'), ['message' => 'Hola'])
            ->assertForbidden();
        $this->get(route('es.editor.assistant.index'))->assertForbidden();

        $this->assertSame(0, Conversation::count());
    }

    private function admin(): User
    {
        return User::factory()->withRole(Role::ALIAS_ADMIN)->create(['approved_at' => now(), 'email_verified_at' => now()]);
    }

    /**
     * The stream, read as the browser reads it.
     *
     * @param  TestResponse<Response>  $response
     * @return list<array{event: string, data: array<string, mixed>}>
     */
    private function events(TestResponse $response): array
    {
        $blocks = array_filter(explode("\n\n", $response->streamedContent()));

        return array_values(array_map(function (string $block): array {
            if (preg_match('/^event: (.+)\ndata: (.+)$/', $block, $match) !== 1) {
                $this->fail("Not a server-sent event: {$block}");
            }

            return ['event' => $match[1], 'data' => json_decode($match[2], true)];
        }, $blocks));
    }
}
