<?php

namespace Tests\Feature;

use App\Assistant\Agents\Planner;
use App\Assistant\Agents\Speaker;
use App\Enums\Level;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rehearsal drives whole conversations through the console channel — the second channel,
 * with no change to the orchestrator — and fails a conversation that offers the wrong guide or
 * puts a link in a reply. Run against the real model, it is the pack's acceptance check; here
 * the model is faked and only the checking is tested.
 */
class AssistantRehearsalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assistant.cache_instructions' => false]);

        // The guide the canned passages name (`FakeRetriever`).
        Guide::forceCreate(['id' => 5, 'locale' => 'es', 'title' => 'Excel desde cero', 'level' => Level::BASIC]);
    }

    public function test_a_conversation_that_offers_the_guide_and_records_the_acceptance_holds(): void
    {
        Planner::fake([
            $this->plan(),
            $this->plan(offer: 5),
            $this->plan(),
            $this->plan(accepted: 5),
        ]);
        Speaker::fake(['Hola', 'Te recomiendo Excel desde cero', 'Sí, empieza de cero', '¡Genial!']);

        $this->artisan('arcadia:rehearse-assistant', ['rehearsal' => 'necesidad-clara'])
            ->expectsOutputToContain('CONVERSION: Excel desde cero')
            ->expectsOutputToContain('Every rehearsal held')
            ->assertSuccessful();
    }

    public function test_a_reply_with_a_link_fails_the_rehearsal(): void
    {
        Planner::fake([$this->plan(offer: 5), $this->plan(), $this->plan()]);
        Speaker::fake(['Te recomiendo Excel desde cero', 'No puedo contarte', 'Mira https://youtube.com/x']);

        $this->artisan('arcadia:rehearse-assistant', ['rehearsal' => 'pregunta-por-el-contenido'])
            ->expectsOutputToContain('pregunta-por-el-contenido: a reply contains a link')
            ->assertFailed();
    }

    public function test_an_unknown_rehearsal_is_refused(): void
    {
        $this->artisan('arcadia:rehearse-assistant', ['rehearsal' => 'nope'])
            ->expectsOutputToContain('No rehearsal [nope]')
            ->assertFailed();
    }

    /**
     * @return array{engagement: int, accepted_guide_id: int|null, offer_guide_id: int|null}
     */
    private function plan(?int $accepted = null, ?int $offer = null): array
    {
        return ['engagement' => 50, 'accepted_guide_id' => $accepted, 'offer_guide_id' => $offer];
    }
}
