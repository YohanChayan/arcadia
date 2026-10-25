<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\InquiryStage;
use App\Enums\Level;
use App\Enums\PromptVariableType;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\PromptTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A guide started from a request: a draft titled by what they asked for, that always says which
 * request it answers — and, once its research prompt is copied, which version and values
 * researched it.
 */
class GuideProvenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]));
    }

    private function researchTemplate(): PromptTemplate
    {
        $template = PromptTemplate::create(['name' => PromptTemplate::RESEARCH]);
        $version = $template->latestVersion();
        foreach ([['topic', PromptVariableType::TEXT], ['level', PromptVariableType::LEVEL], ['goal', PromptVariableType::TEXT]] as $i => [$name, $type]) {
            $version->variables()->create(['name' => $name, 'label' => $name, 'type' => $type, 'required' => false, 'position' => $i + 1]);
        }

        return $template;
    }

    public function test_a_request_starts_a_draft_guide_without_a_prompt(): void
    {
        $inquiry = Inquiry::create(['locale' => 'es', 'topic' => 'excel para mi entrevista', 'purpose' => 'job', 'contact' => 'ana@example.com', 'name' => 'Ana']);

        $response = $this->post("/es/editor/requests/{$inquiry->id}/guide");

        $guide = Guide::sole();
        $response->assertRedirect("/es/editor/guides/{$guide->id}/content");

        $this->assertSame('es', $guide->locale);
        $this->assertSame('Excel para mi entrevista', $guide->title);
        $this->assertSame(Level::BASIC, $guide->level);
        $this->assertSame(GuideStatus::DRAFT, $guide->status);
        $this->assertSame($inquiry->id, $guide->inquiry_id);
        $this->assertNull($guide->prompt_version_id);
        $this->assertSame(InquiryStage::GUIDE, $inquiry->fresh()->stage());

        // Its research prompt starts from what the request says.
        $this->researchTemplate();
        $this->get("/es/editor/guides/{$guide->id}/content")->assertInertia(fn ($page) => $page
            ->where('research.values.topic', 'excel para mi entrevista')
            ->where('research.values.learner_name', 'Ana')
            ->where('research.values.purpose', __('arcadia.landing.ask.purposes.job')));
    }

    public function test_a_request_prompted_before_the_move_still_hands_its_prompt_on(): void
    {
        $version = $this->researchTemplate()->latestVersion();
        $inquiry = Inquiry::create(['locale' => 'es', 'topic' => 'excel', 'contact' => 'ana@example.com']);
        $inquiry->forceFill([
            'prompt_version_id' => $version->id,
            'prompt_values' => ['topic' => 'excel para mi entrevista', 'level' => 'intermediate', 'goal' => 'Conseguir trabajo'],
            'prompted_at' => now(),
        ])->save();

        $this->post("/es/editor/requests/{$inquiry->id}/guide");

        $guide = Guide::sole();
        $this->assertSame('Excel para mi entrevista', $guide->title);
        $this->assertSame(Level::INTERMEDIATE, $guide->level);
        // The prompt's goal, as written for this request: nothing registered to match it to.
        $this->assertSame(['Conseguir trabajo'], $guide->goals);
        $this->assertSame($version->id, $guide->prompt_version_id);
        $this->assertSame($inquiry->fresh()->prompt_values, $guide->prompt_values);
    }

    public function test_one_request_starts_one_guide(): void
    {
        $inquiry = Inquiry::create(['locale' => 'es', 'topic' => 'excel', 'contact' => 'ana@example.com']);

        $this->post("/es/editor/requests/{$inquiry->id}/guide");
        $this->post("/es/editor/requests/{$inquiry->id}/guide");

        $this->assertSame(1, Guide::count());
        $this->get('/es/editor/requests')->assertInertia(fn ($page) => $page->where('inquiries.data.0.guide.title', 'Excel'));
    }

    public function test_another_languages_request_cannot_start_a_guide_here(): void
    {
        $english = Inquiry::create(['locale' => 'en', 'topic' => 'Excel', 'contact' => 'bob@example.com']);

        $this->post("/es/editor/requests/{$english->id}/guide")->assertNotFound();
    }

    public function test_the_guide_says_what_researched_it(): void
    {
        $version = $this->researchTemplate()->latestVersion();
        $inquiry = Inquiry::create(['locale' => 'es', 'topic' => 'excel', 'contact' => 'ana@example.com', 'name' => 'Ana']);
        $this->post("/es/editor/requests/{$inquiry->id}/guide");
        $guide = Guide::sole();

        $this->post("/es/editor/guides/{$guide->id}/prompt", [
            'version_id' => $version->id,
            'values' => ['topic' => 'excel', 'level' => 'intermediate'],
        ])->assertSessionHasNoErrors();

        $this->get("/es/editor/guides/{$guide->id}/edit")->assertInertia(fn ($page) => $page
            ->where('provenance.template', PromptTemplate::RESEARCH)
            ->where('provenance.number', 1)
            ->where('provenance.values.level', 'intermediate')
            ->where('provenance.inquiry.name', 'Ana'));

        // A guide written by hand, never researched, has none.
        $byHand = Guide::create(['locale' => 'es', 'title' => 'A mano', 'level' => Level::BASIC]);
        $this->get("/es/editor/guides/{$byHand->id}/edit")->assertInertia(fn ($page) => $page->where('provenance', null));
    }
}
