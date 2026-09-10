<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Models\SparBanner;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * Banner slider on the mobi tracker (spec AC-2/AC-4): a patient sees THEIR
 * group's live banners after consent; impressions increment; not another
 * group's banners; not before consent.
 */
class SparBannerSliderTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacyGroup $groupB;
    private SparPharmacy $pharmA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['spar.link.require_otp_reverify' => false]);
        $this->groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $this->groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->pharmA = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A', 'spar_store_id' => 'A', 'is_active' => true]);

        SparBanner::create(['group_id' => $this->groupA->id, 'title' => 'GROUP-A-AD', 'image_path' => 'spar-banners/a.webp', 'is_active' => true, 'sort_order' => 1]);
        SparBanner::create(['group_id' => $this->groupB->id, 'title' => 'GROUP-B-AD', 'image_path' => 'spar-banners/b.webp', 'is_active' => true, 'sort_order' => 1]);
    }

    private function consentedPatient(): SparPatient
    {
        return SparPatient::create([
            'spar_pharmacy_id' => $this->pharmA->id, 'profile_code' => '990001', 'dependent_code' => '00',
            'first_name' => 'Priya', 'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);
    }

    public function test_consented_patient_sees_own_group_banner_and_records_impression(): void
    {
        $patient = $this->consentedPatient();
        app(SparPatientSession::class)->establish($patient);

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'dashboard')
            ->assertSee('spar-banners/a.webp');   // group A's banner image url rendered

        // Impression recorded for the shown banner.
        $this->assertSame(1, SparBanner::where('group_id', $this->groupA->id)->first()->impressions);
        // Group B's banner was NOT shown → no impression.
        $this->assertSame(0, SparBanner::where('group_id', $this->groupB->id)->first()->impressions);
    }

    public function test_no_banners_before_consent(): void
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmA->id, 'profile_code' => '990002', 'dependent_code' => '00',
            'first_name' => 'Raj', 'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'pending',
        ]);
        app(SparPatientSession::class)->establish($patient);

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'consent')       // sitting on the consent gate
            ->assertDontSee('spar-banners/a.webp');

        // No impression recorded pre-consent.
        $this->assertSame(0, SparBanner::where('group_id', $this->groupA->id)->first()->impressions);
    }
}
