<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparDuplicateMerger;

/**
 * National identity, Phase 4 — automated duplicate merge with the phone safety
 * valve. Unambiguous duplicates collapse into one; phone conflicts are skipped
 * and flagged for review (never silently fused).
 */
class SparMergeDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $a;
    private SparPharmacy $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = SparPharmacy::create(['name' => 'A', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['name' => 'B', 'spar_store_id' => 'B', 'is_active' => true]);
    }

    private function dup(SparPharmacy $pharmacy, string $profile, ?string $phone): SparPatient
    {
        $p = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => $profile, 'dependent_code' => '00',
            'cellphone' => $phone, 'is_primary_member' => true, 'is_active' => true,
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $p->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'S-' . $pharmacy->id, 'status' => 'active',
            'total_dispenses' => 6, 'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [],
        ]);

        return $p;
    }

    public function test_unambiguous_duplicates_merge_into_one(): void
    {
        // Same profile at two stores, phones agree (one blank) → unambiguous.
        $keep = $this->dup($this->a, '990001', '0821110001');
        $dupe = $this->dup($this->b, '990001', null);

        $stats = (new SparDuplicateMerger())->mergeAll();

        $this->assertSame(1, $stats['merged']);
        $this->assertSame(0, $stats['skipped']);

        // Survivor keeps both journeys; loser retired.
        $this->assertSame(2, SparPrescriptionJourney::where('spar_patient_id', $keep->id)->count());
        $this->assertFalse($dupe->fresh()->is_active);
        $this->assertSame($keep->id, $dupe->fresh()->metadata['merged_into']);
        $this->assertSame(1, SparPatient::where('is_active', true)->count());
    }

    public function test_conflicting_phones_are_skipped_and_flagged(): void
    {
        // Same profile, DIFFERENT phones → ambiguous → skip + flag, don't merge.
        $x = $this->dup($this->a, '990002', '0820000001');
        $y = $this->dup($this->b, '990002', '0829999999');

        $stats = (new SparDuplicateMerger())->mergeAll();

        $this->assertSame(0, $stats['merged']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertTrue($x->fresh()->needs_identity_review);
        $this->assertTrue($y->fresh()->is_active); // NOT merged
        $this->assertSame(2, SparPatient::where('is_active', true)->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->dup($this->a, '990003', '0820000003');
        $this->dup($this->b, '990003', null);

        (new SparDuplicateMerger())->mergeAll(dryRun: true);

        $this->assertSame(2, SparPatient::where('is_active', true)->count());
    }
}
