<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CommunicationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POPIA marketing vs transactional opt-out split.
 * - Transactional/clinical messages are never blocked.
 * - Marketing is blocked only after an explicit opt-out.
 * - A marketing opt-out must NOT suppress transactional messages.
 */
class CommunicationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private CommunicationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefs = new CommunicationPreferenceService();
    }

    public function test_transactional_always_allowed(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $this->assertTrue($this->prefs->canReceive($user, CommunicationPreferenceService::TRANSACTIONAL));
    }

    public function test_marketing_allowed_by_default_until_opt_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $this->assertTrue($this->prefs->canReceive($user, CommunicationPreferenceService::MARKETING));
    }

    public function test_marketing_blocked_after_opt_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $this->prefs->optOutOfMarketing($user);

        $this->assertTrue($this->prefs->hasOptedOutOfMarketing($user));
        $this->assertFalse($this->prefs->canReceive($user, CommunicationPreferenceService::MARKETING));
    }

    public function test_marketing_opt_out_does_not_suppress_transactional(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $this->prefs->optOutOfMarketing($user);

        // The whole point: opting out of marketing must NOT kill safety messages.
        $this->assertTrue($this->prefs->canReceive($user, CommunicationPreferenceService::TRANSACTIONAL));
    }

    public function test_opt_in_after_opt_out_reenables_marketing(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $this->prefs->optOutOfMarketing($user);
        $this->prefs->optInToMarketing($user);

        $this->assertFalse($this->prefs->hasOptedOutOfMarketing($user));
        $this->assertTrue($this->prefs->canReceive($user, CommunicationPreferenceService::MARKETING));
    }
}
