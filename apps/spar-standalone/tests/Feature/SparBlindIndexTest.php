<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;

/**
 * National identity, Phase 1 — blind index (keyed HMAC) foundation.
 * Matchable in SQL, non-reversible, and the plaintext stays encrypted at rest.
 */
class SparBlindIndexTest extends TestCase
{
    use RefreshDatabase;

    private int $pharmacyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pharmacyId = SparPharmacy::create([
            'name' => 'Test Pharmacy', 'spar_store_id' => 'T1', 'is_active' => true,
        ])->id;
    }

    private function make(array $attrs): SparPatient
    {
        return SparPatient::create(array_merge([
            'spar_pharmacy_id' => $this->pharmacyId,
            'dependent_code' => '00',
            'is_primary_member' => true,
            'is_active' => true,
        ], $attrs));
    }

    public function test_hash_is_deterministic_and_matchable(): void
    {
        $p = $this->make([
            'profile_code' => '990001',
            'cellphone' => '072 123 4567',
        ]);

        // Same normalised input → same hash (space/format-insensitive).
        $this->assertNotNull($p->profile_code_hash);
        $this->assertSame(SparPatient::blindIndex('990001', 'profile'), $p->profile_code_hash);
        $this->assertSame(SparPatient::blindIndex('0721234567', 'phone'), $p->cellphone_hash);

        // Matchable via the hash column without decrypting.
        $found = SparPatient::where('profile_code_hash', SparPatient::blindIndex('990001', 'profile'))->first();
        $this->assertTrue($found->is($p));
    }

    public function test_hash_is_not_the_plaintext(): void
    {
        $p = $this->make(['profile_code' => 'ABC-123']);

        $this->assertNotSame('ABC-123', $p->profile_code_hash);
        $this->assertSame(64, strlen($p->profile_code_hash)); // sha256 hex
    }

    public function test_plaintext_stays_encrypted_at_rest(): void
    {
        $p = $this->make([
            'profile_code' => '990009',
            'cellphone' => '0820000000',
        ]);

        // Raw column value must be ciphertext, not '990009'.
        $raw = DB::table('spar_patients')->where('id', $p->id)->value('profile_code');
        $this->assertNotSame('990009', $raw);
        $this->assertNotEmpty($raw);
        // Model still decrypts transparently.
        $this->assertSame('990009', $p->fresh()->profile_code);
    }

    public function test_empty_phone_hashes_to_null_not_a_constant(): void
    {
        $a = $this->make(['profile_code' => 'P1', 'dependent_code' => '01', 'is_primary_member' => false]);
        $b = $this->make(['profile_code' => 'P2', 'dependent_code' => '02', 'is_primary_member' => false]);

        // No phone → null hash → two contactless patients don't collide.
        $this->assertNull($a->cellphone_hash);
        $this->assertNull($b->cellphone_hash);
    }

    public function test_backfill_populates_missing_hashes(): void
    {
        $p = $this->make(['profile_code' => '990002']);
        // Simulate a legacy row with no hash.
        $p->forceFill(['profile_code_hash' => null, 'cellphone_hash' => null])->saveQuietly();
        $this->assertNull($p->fresh()->profile_code_hash);

        $this->artisan('spar:backfill-blind-index')->assertExitCode(0);

        $this->assertSame(SparPatient::blindIndex('990002', 'profile'), $p->fresh()->profile_code_hash);
    }
}
