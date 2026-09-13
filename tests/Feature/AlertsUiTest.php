<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\AlertsBoard;
use App\Models\Alert;
use App\Models\User;
use Database\Seeders\AlertDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 4 — Alerts morning-list UI.
 */
class AlertsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AlertDefinitionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function anAlert(array $overrides = []): Alert
    {
        return Alert::raise(array_merge([
            'definition_code' => 'payments.failed', 'severity' => 'warning', 'title' => 'Payment failed',
            'subject_type' => 'payment', 'subject_id' => 1, 'dedupe_key' => 'payments.failed:1',
        ], $overrides));
    }

    public function test_admin_can_view_alerts(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.alerts'))
            ->assertOk()
            ->assertSeeLivewire(AlertsBoard::class);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Patient]))
            ->get(route('admin.alerts'))
            ->assertForbidden();
    }

    public function test_open_alert_is_listed(): void
    {
        $this->anAlert();

        Livewire::actingAs($this->admin())
            ->test(AlertsBoard::class)
            ->assertSee('Payment failed');
    }

    public function test_acknowledge_resolve_and_snooze(): void
    {
        $alert = $this->anAlert();

        $c = Livewire::actingAs($this->admin())->test(AlertsBoard::class);

        $c->call('acknowledge', $alert->id);
        $this->assertSame(AlertStatus::Acknowledged, $alert->fresh()->status);

        $c->call('snooze', $alert->id);
        $this->assertSame(AlertStatus::Snoozed, $alert->fresh()->status);

        $c->call('resolve', $alert->id);
        $this->assertSame(AlertStatus::Resolved, $alert->fresh()->status);
    }

    public function test_assign_to_me(): void
    {
        $alert = $this->anAlert();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(AlertsBoard::class)->call('assignToMe', $alert->id);

        $this->assertSame($admin->id, $alert->fresh()->assigned_to);
    }

    public function test_add_comment(): void
    {
        $alert = $this->anAlert();

        Livewire::actingAs($this->admin())
            ->test(AlertsBoard::class)
            ->call('select', $alert->id)
            ->set('commentBody', 'Contacted the patient, retrying card.')
            ->call('addComment');

        $this->assertSame(1, $alert->comments()->count());
    }

    public function test_run_scan_button_raises_alerts(): void
    {
        // A failed payment exists; the manual scan should surface it.
        \App\Models\Payment::create([
            'patient_id' => User::factory()->create(['role' => UserRole::Patient])->id,
            'provider' => 'payfast', 'amount' => 5000, 'status' => 'failed',
        ]);

        Livewire::actingAs($this->admin())
            ->test(AlertsBoard::class)
            ->call('runScan');

        $this->assertGreaterThanOrEqual(1, Alert::where('definition_code', 'payments.failed')->count());
    }

    public function test_severity_filter_narrows_the_list(): void
    {
        $this->anAlert(['dedupe_key' => 'payments.failed:1', 'subject_id' => 1, 'severity' => 'warning']);
        $this->anAlert(['definition_code' => 'orders.stale_critical', 'dedupe_key' => 'orders.stale_critical:2', 'subject_id' => 2, 'severity' => 'critical', 'title' => 'Critical stale']);

        Livewire::actingAs($this->admin())
            ->test(AlertsBoard::class)
            ->set('severities', ['critical'])
            ->assertSee('Critical stale')
            ->assertDontSee('Payment failed');
    }
}
