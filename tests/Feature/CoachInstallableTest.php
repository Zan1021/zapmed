<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coach app v3 piece that needs nobody's input: the coach app can be put on a phone's home screen. */
class CoachInstallableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_coach_pages_link_the_install_file_and_start_the_service_worker(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id, 'started_at' => now()]);
        CoachProfile::create(['user_id' => $user->id, 'onboarded_at' => now()]);

        $this->actingAs($user)->get(route('coach.today'))->assertOk()
            ->assertSee('<link rel="manifest" href="/coach.webmanifest">', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee("serviceWorker.register('/sw.js')", false);
    }

    public function test_the_install_file_is_valid_and_every_icon_exists(): void
    {
        $m = json_decode(file_get_contents(public_path('coach.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/coach', $m['start_url']);
        $this->assertSame('/coach', $m['scope']);
        $sizes = [];
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $sizes[] = $icon['sizes'];
        }
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_the_offline_page_the_service_worker_falls_back_to_exists(): void
    {
        $this->get('/offline')->assertOk();
        $this->assertStringContainsString("'/offline'", file_get_contents(public_path('sw.js')));
    }
}