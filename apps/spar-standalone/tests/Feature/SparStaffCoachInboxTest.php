<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\StaffCoachInbox;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparCoachService;

/**
 * Pharmacy-staff Health Coach INBOX (StaffCoachInbox). Verifies the store-scoped
 * conversation list, the unread badge count, and that opening a conversation is
 * scope-gated and clears its staff badge (via the reused StaffCoachMessages).
 */
class SparStaffCoachInboxTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $a;
    private SparPharmacy $b;
    private SparPatient $patientA;
    private SparPatient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        $groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->a = SparPharmacy::create(['group_id' => $groupA->id, 'name' => 'Wapadrand', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'Faraway', 'spar_store_id' => 'B', 'is_active' => true]);

        $this->patientA = $this->makePatient($this->a, '990001', 'Priya', 'Naidoo');
        $this->patientB = $this->makePatient($this->b, '990002', 'Johan', 'Van Wyk');
    }

    private function makePatient(SparPharmacy $pharmacy, string $profile, string $first, string $last): SparPatient
    {
        $p = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => $profile, 'dependent_code' => '00',
            'first_name' => $first, 'last_name' => $last,
            'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $p->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => $profile . '-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 1, 'start_date' => now()->subMonth(), 'medications' => [],
        ]);

        return $p;
    }

    private function staff(SparPharmacy $pharmacy): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Nurse Joy', 'email' => 'joy' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $pharmacy->id, 'is_active' => true,
        ]);
    }

    private function coach(): SparCoachService
    {
        return app(SparCoachService::class);
    }

    public function test_inbox_lists_only_conversations_for_the_staffers_pharmacy(): void
    {
        // A conversation at each store (patient messages -> staff has unread).
        $this->coach()->postPatientMessage($this->coach()->openConversation($this->patientA, $this->a->id), 'Hi from A');
        $this->coach()->postPatientMessage($this->coach()->openConversation($this->patientB, $this->b->id), 'Hi from B');

        $this->actingAs($this->staff($this->a));

        $conversations = Livewire::test(StaffCoachInbox::class)->get('conversations');

        $this->assertCount(1, $conversations);
        $this->assertSame($this->patientA->id, $conversations->first()->spar_patient_id);
    }

    public function test_unread_total_badge_counts_only_scoped_conversations(): void
    {
        // 2 unread patient messages at A, 1 at B.
        $cA = $this->coach()->openConversation($this->patientA, $this->a->id);
        $this->coach()->postPatientMessage($cA, 'One');
        $this->coach()->postPatientMessage($cA, 'Two');
        $this->coach()->postPatientMessage($this->coach()->openConversation($this->patientB, $this->b->id), 'B only');

        $this->actingAs($this->staff($this->a));

        $this->assertSame(2, Livewire::test(StaffCoachInbox::class)->get('unreadTotal'));
    }

    public function test_selecting_a_conversation_opens_thread_and_clears_staff_badge(): void
    {
        $cA = $this->coach()->openConversation($this->patientA, $this->a->id);
        $this->coach()->postPatientMessage($cA, 'Please call me');
        $cA->refresh();
        $this->assertSame(1, $cA->staff_unread_count);

        $this->actingAs($this->staff($this->a));

        Livewire::test(StaffCoachInbox::class)
            ->call('select', $cA->id)
            ->assertSet('activePatientId', $this->patientA->id)
            ->assertSet('activePharmacyId', $this->a->id);

        // Opening the thread (StaffCoachMessages mount) marks it read.
        $cA->refresh();
        $this->assertSame(0, $cA->staff_unread_count);
    }

    public function test_cannot_select_a_conversation_outside_scope(): void
    {
        $cB = $this->coach()->openConversation($this->patientB, $this->b->id);
        $this->coach()->postPatientMessage($cB, 'B message');

        $this->actingAs($this->staff($this->a));

        Livewire::test(StaffCoachInbox::class)
            ->call('select', $cB->id)
            ->assertStatus(403);
    }

    public function test_inbox_route_is_reachable_by_staff(): void
    {
        $this->actingAs($this->staff($this->a));

        $this->get(route('spar.coach.inbox'))
            ->assertOk()
            ->assertSee('Health Coach');
    }
}
