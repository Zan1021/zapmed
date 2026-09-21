<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\PatientCoachMessages;
use Zapmed\SparCore\Livewire\StaffCoachMessages;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Models\SparProductSuggestion;
use Zapmed\SparCore\Services\SparCoachService;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * Health Coach v1 (spec spar-health-coach AC-1..AC-12). Consent-gated
 * patient<->pharmacy-staff messenger + suggest-to-basket. Coach = pharmacy staff.
 */
class SparHealthCoachTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $a;
    private SparPharmacy $b;
    private SparPatient $primary;

    protected function setUp(): void
    {
        parent::setUp();

        $groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->a = SparPharmacy::create(['group_id' => $groupA->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'Faraway', 'spar_store_id' => 'B', 'is_active' => true]);

        $this->primary = SparPatient::create([
            'spar_pharmacy_id' => $this->a->id,
            'profile_code' => '990001', 'dependent_code' => '00',
            'first_name' => 'Priya', 'last_name' => 'Naidoo',
            'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);
        // A journey so visibleToCurrentActor()/scopeForPharmacy resolves the patient at store A.
        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->primary->id, 'spar_pharmacy_id' => $this->a->id,
            'script_number' => 'ACTIVE-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 2, 'start_date' => now()->subMonths(2),
            'medications' => [['name' => 'CO-COPALIA TAB 28']],
        ]);
    }

    private function staff(?int $pharmacyId = null): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Nurse Joy', 'email' => 'joy' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $pharmacyId ?? $this->a->id, 'is_active' => true,
        ]);
    }

    private function coach(): SparCoachService
    {
        return app(SparCoachService::class);
    }

    private function establishPatientSession(): void
    {
        // No-login patient session (as the signed tracker link would).
        app(SparPatientSession::class)->establish($this->primary);
        // Mark verified so the tracker resolves straight to the dashboard/consent.
        app(SparPatientSession::class)->markVerified();
    }

    // ---- FR-1 conversation --------------------------------------------------

    public function test_one_open_conversation_per_patient_pharmacy(): void
    {
        $c1 = $this->coach()->openConversation($this->primary, $this->a->id);
        $c2 = $this->coach()->openConversation($this->primary, $this->a->id);

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(1, SparConversation::count());
        $this->assertSame($this->primary->id, $c1->spar_patient_id);
    }

    public function test_conversation_binds_to_primary_member(): void
    {
        $dependant = SparPatient::create([
            'spar_pharmacy_id' => $this->a->id, 'profile_code' => '990001', 'dependent_code' => '01',
            'first_name' => 'Raj', 'is_primary_member' => false, 'is_active' => true,
        ]);

        $c = $this->coach()->openConversation($dependant, $this->a->id);

        $this->assertSame($this->primary->id, $c->spar_patient_id);
    }

    // ---- FR-2/3/4/6 messaging + unread -------------------------------------

    public function test_staff_and_patient_messages_and_unread_accounting(): void
    {
        $c = $this->coach()->openConversation($this->primary, $this->a->id);

        $this->coach()->postStaffMessage($c, 'Hi Priya, how are your meds?', ['id' => 1, 'name' => 'Nurse Joy', 'role' => 'pharmacy_staff']);
        $c->refresh();
        $this->assertSame(1, $c->patient_unread_count); // patient owes a read

        $this->coach()->postPatientMessage($c, 'All good, thanks!');
        $c->refresh();
        $this->assertSame(1, $c->staff_unread_count);

        // Patient reads -> their badge clears; staff message marked read.
        $this->coach()->markRead($c, 'patient');
        $c->refresh();
        $this->assertSame(0, $c->patient_unread_count);

        $this->assertSame(2, $c->messages()->whereIn('direction', ['from_staff', 'from_patient'])->count());
    }

    // ---- FR-3 body is encrypted (NFR-5) ------------------------------------

    public function test_message_body_is_encrypted_at_rest(): void
    {
        $c = $this->coach()->openConversation($this->primary, $this->a->id);
        $this->coach()->postPatientMessage($c, 'Secret health detail');

        $raw = \Illuminate\Support\Facades\DB::table('spar_messages')->latest('id')->first();
        $this->assertNotSame('Secret health detail', $raw->body);
        $this->assertSame('Secret health detail', $c->messages()->latest('id')->first()->body);
    }

    // ---- FR-7/8/9 suggest -> accept attaches to order (basket) -------------

    public function test_suggest_then_accept_attaches_item_and_posts_system_line(): void
    {
        $c = $this->coach()->openConversation($this->primary, $this->a->id);

        $msg = $this->coach()->suggestProduct($c, ['id' => 1, 'name' => 'Nurse Joy', 'role' => 'pharmacy_staff'], 'Magnesium', 5900, 'Helps with cramps');
        $suggestion = $msg->productSuggestion;
        $this->assertSame('offered', $suggestion->status);

        $accepted = $this->coach()->acceptSuggestion($suggestion);

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->spar_order_id);
        $this->assertNotNull($accepted->spar_order_item_id);

        $order = SparOrder::find($accepted->spar_order_id);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Magnesium', $order->items()->first()->product_name);
        $this->assertSame(5900, $order->items()->first()->price_cents);

        // A system line records the acceptance in-thread. body is encrypted, so
        // we cannot LIKE-match it in SQL — fetch system messages and check in PHP.
        $systemLine = $c->messages()->where('direction', 'system')->get()
            ->first(fn ($m) => str_contains($m->body, 'Magnesium'));
        $this->assertNotNull($systemLine);
    }

    public function test_accept_reuses_existing_pending_order(): void
    {
        $existing = SparOrder::create([
            'spar_patient_id' => $this->primary->id, 'spar_pharmacy_id' => $this->a->id,
            'type' => 'collection', 'status' => 'requested',
        ]);

        $c = $this->coach()->openConversation($this->primary, $this->a->id);
        $msg = $this->coach()->suggestProduct($c, ['id' => 1, 'name' => 'Joy', 'role' => 'pharmacy_staff'], 'Vitamin D', 3000, null);
        $accepted = $this->coach()->acceptSuggestion($msg->productSuggestion);

        $this->assertSame($existing->id, $accepted->spar_order_id);
        $this->assertSame(1, SparOrder::count()); // no extra basket created
    }

    public function test_decline_marks_declined_and_captures_signal(): void
    {
        $c = $this->coach()->openConversation($this->primary, $this->a->id);
        $msg = $this->coach()->suggestProduct($c, ['id' => 1, 'name' => 'Joy', 'role' => 'pharmacy_staff'], 'Omega 3', 8000, null);

        $declined = $this->coach()->declineSuggestion($msg->productSuggestion);

        $this->assertSame('declined', $declined->status);
        $this->assertNotNull($declined->declined_at);
        $this->assertSame(0, SparOrder::count()); // decline creates no order
    }

    // ---- FR-12 consent hard-stop -------------------------------------------

    public function test_nudge_not_delivered_to_non_consented_patient(): void
    {
        $this->primary->update(['consent_status' => 'opted_out', 'consent_given_at' => null]);

        $c = $this->coach()->openConversation($this->primary, $this->a->id);
        // Posting a staff message triggers the dispatcher nudge, which must not
        // deliver to a non-consented patient. The message is still recorded
        // (staff can leave a note), but no exception + dispatcher returns false.
        $msg = $this->coach()->postStaffMessage($c, 'Please opt in', ['id' => 1, 'name' => 'Joy', 'role' => 'pharmacy_staff']);

        $this->assertNotNull($msg->id);
        // MessagingDispatcher::send() returns false for non-consented — asserted
        // directly for clarity.
        $this->assertFalse(app(\Zapmed\SparCore\Services\MessagingDispatcher::class)->send($this->primary->fresh(), ['body' => 'x']));
    }

    // ---- AC-2 staff Livewire surface + AC-8 scope --------------------------

    public function test_staff_can_open_and_send_via_livewire(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(StaffCoachMessages::class, ['patientId' => $this->primary->id])
            ->set('body', 'Hello from the pharmacy')
            ->call('send')
            ->assertSee('Hello from the pharmacy');

        $this->assertTrue(
            SparConversation::first()->messages()->where('direction', 'from_staff')->exists()
        );
    }

    public function test_staff_out_of_scope_is_blocked(): void
    {
        // Patient only at store B; staff scoped to store A cannot open the thread.
        $other = SparPatient::create([
            'spar_pharmacy_id' => $this->b->id, 'profile_code' => 'OTHER', 'dependent_code' => '00',
            'is_primary_member' => true, 'is_active' => true,
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $other->id, 'spar_pharmacy_id' => $this->b->id,
            'script_number' => 'B-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [],
        ]);

        $this->actingAs($this->staff());

        Livewire::test(StaffCoachMessages::class, ['patientId' => $other->id])
            ->assertStatus(403);
    }

    // ---- AC-1 patient Livewire surface + AC-5 add-to-order -----------------

    public function test_patient_can_reply_and_add_suggestion_to_order_via_livewire(): void
    {
        // Staff suggests first.
        $c = $this->coach()->openConversation($this->primary, $this->a->id);
        $msg = $this->coach()->suggestProduct($c, ['id' => 1, 'name' => 'Joy', 'role' => 'pharmacy_staff'], 'Magnesium', 5900, null);
        $suggestionId = $msg->productSuggestion->id;

        $this->establishPatientSession();

        Livewire::test(PatientCoachMessages::class)
            ->set('body', 'Thanks Joy!')
            ->call('send')
            ->assertSee('Thanks Joy!')
            ->call('accept', $suggestionId);

        $suggestion = SparProductSuggestion::find($suggestionId);
        $this->assertSame('accepted', $suggestion->status);
        $this->assertNotNull($suggestion->spar_order_id);
        $this->assertSame('Magnesium', SparOrder::find($suggestion->spar_order_id)->items()->first()->product_name);
    }

    public function test_package_purity_no_host_classes_in_coach_code(): void
    {
        $files = [
            base_path('../../packages/spar-core/src/Services/SparCoachService.php'),
            base_path('../../packages/spar-core/src/Livewire/StaffCoachMessages.php'),
            base_path('../../packages/spar-core/src/Livewire/PatientCoachMessages.php'),
            base_path('../../packages/spar-core/src/Models/SparConversation.php'),
            base_path('../../packages/spar-core/src/Models/SparMessage.php'),
            base_path('../../packages/spar-core/src/Models/SparProductSuggestion.php'),
            base_path('../../packages/spar-core/src/Models/SparOrderItem.php'),
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
            $src = file_get_contents($file);
            $this->assertStringNotContainsString('App\\Models\\User', $src, "Host User referenced in {$file}");
            $this->assertStringNotContainsString('App\\Models\\Prescription', $src, "Host Prescription referenced in {$file}");
            $this->assertStringNotContainsString('UserRole', $src, "UserRole referenced in {$file}");
        }
    }
}
