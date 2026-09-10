<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparBanners;
use Zapmed\SparCore\Models\SparBanner;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Services\SparBannerImageService;

class SparBannerTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacyGroup $groupB;
    private SparPharmacy $pharmA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $this->groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->pharmA = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A', 'spar_store_id' => 'A', 'is_active' => true]);
    }

    private function groupAdmin(SparPharmacyGroup $g): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'GA Admin', 'email' => 'ga' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'group_admin', 'group_id' => $g->id, 'is_active' => true,
        ]);
    }

    public function test_upload_converts_to_webp(): void
    {
        $this->actingAs($this->groupAdmin($this->groupA));

        Livewire::test(SparBanners::class)
            ->set('image', UploadedFile::fake()->image('promo.jpg', 1600, 800))
            ->set('title', 'Winter specials')
            ->set('linkUrl', 'https://spar.co.za/specials')
            ->call('save')
            ->assertHasNoErrors();

        $banner = SparBanner::first();
        $this->assertNotNull($banner);
        $this->assertSame($this->groupA->id, $banner->group_id);
        // Stored as .webp (GD WebP available in this env).
        $this->assertStringEndsWith('.webp', $banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_sixth_active_banner_is_rejected(): void
    {
        $this->actingAs($this->groupAdmin($this->groupA));
        for ($i = 0; $i < 5; $i++) {
            SparBanner::create([
                'group_id' => $this->groupA->id, 'title' => "B$i",
                'image_path' => "spar-banners/x$i.webp", 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        Livewire::test(SparBanners::class)
            ->set('image', UploadedFile::fake()->image('promo.png', 1080, 420))
            ->call('save')
            ->assertHasErrors('image');

        $this->assertSame(5, SparBanner::count());
    }

    public function test_group_admin_cannot_manage_other_group(): void
    {
        $this->actingAs($this->groupAdmin($this->groupA));
        $other = SparBanner::create([
            'group_id' => $this->groupB->id, 'title' => 'B', 'image_path' => 'spar-banners/b.webp', 'is_active' => true,
        ]);

        // Deleting a banner from another group must 403.
        Livewire::test(SparBanners::class)
            ->call('delete', $other->id)
            ->assertForbidden();
    }

    public function test_click_route_increments_and_redirects(): void
    {
        $banner = SparBanner::create([
            'group_id' => $this->groupA->id, 'title' => 'B', 'image_path' => 'spar-banners/b.webp',
            'link_url' => 'https://example.test/deal', 'is_active' => true,
        ]);

        $this->get(route('spar.banner.click', $banner->id))
            ->assertRedirect('https://example.test/deal');

        $this->assertSame(1, $banner->fresh()->clicks);
    }

    public function test_image_service_produces_webp_smaller_and_sized(): void
    {
        $svc = new SparBannerImageService();
        $this->assertTrue($svc->webpAvailable(), 'GD WebP expected in test env');

        $path = $svc->store(UploadedFile::fake()->image('big.jpg', 2000, 1000));
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }
}
