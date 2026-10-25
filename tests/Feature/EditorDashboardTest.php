<?php

namespace Tests\Feature;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The editor's home is this language's dashboard: every figure counted from this language's own rows.
 */
class EditorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_counts_this_hosts_requests_and_guides(): void
    {
        Queue::fake();
        // Absolute guide URLs take their scheme and port from `APP_URL`; pin production's.
        config(['app.url' => 'https://arcadia.yhndevside.com']);

        $this->actingAs(User::factory()->withRole(Role::ALIAS_ADMIN)->create([
            'approved_at' => now(), 'email_verified_at' => now(),
        ]));

        Inquiry::create(['locale' => 'es', 'topic' => 'Excel', 'contact' => '0414 123 4567']);
        $answered = Inquiry::create(['locale' => 'es', 'topic' => 'Canva', 'contact' => 'ana@example.com']);
        Inquiry::create(['locale' => 'en', 'topic' => 'English', 'contact' => 'bob@example.com']);

        Guide::create(['locale' => 'es', 'title' => 'Excel', 'level' => Level::BASIC, 'status' => GuideStatus::PUBLISHED]);
        // A request with its guide started is no longer pending.
        Guide::create(['locale' => 'es', 'title' => 'Canva', 'level' => Level::BASIC])->forceFill(['inquiry_id' => $answered->id])->save();
        Guide::create(['locale' => 'en', 'title' => 'English', 'level' => Level::BASIC]);

        $this->get('/es/editor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('editor/Home')
                ->where('siteUrl', 'https://arcadia.yhndevside.com/es')
                ->where('stats.inquiries', ['pending' => 1, 'total' => 2])
                ->where('stats.guides', ['published' => 1, 'drafts' => 1, 'total' => 2])
                ->where('stats.price', '$3')
                ->has('latestInquiries', 2)
                ->where('latestInquiries.0.reply_url', fn ($url) => str_starts_with($url, 'https://wa.me/') || str_starts_with($url, 'mailto:'))
                ->has('recentGuides', 2));
    }
}
