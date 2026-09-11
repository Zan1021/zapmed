<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparBanner;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Services\SparBannerImageService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Group promo banner management (spec FR-2). Group-admins manage their OWN
 * group's slider; super-admin may pick any group. Scope-enforced. Uploads are
 * converted to WebP by SparBannerImageService.
 */
class SparBanners extends Component
{
    use WithFileUploads, LogsSparActivity;

    public ?int $groupId = null;      // the group whose banners we manage
    public $image;                    // upload
    public string $title = '';
    public string $linkUrl = '';
    public string $flash = '';

    public function mount(): void
    {
        $identity = app(SparIdentityProvider::class);

        // Only super-admin or group-admin may manage banners.
        abort_unless($identity->isSuperAdmin() || $identity->currentRole() === 'group_admin', 403);

        // Group-admin is locked to their own group; super-admin defaults to the
        // first group and can switch.
        $this->groupId = $identity->currentGroupId()
            ?? SparPharmacyGroup::orderBy('name')->value('id');
    }

    protected function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'title' => 'nullable|string|max:120',
            'linkUrl' => 'nullable|url|max:500',
        ];
    }

    /** Guard: the current actor may manage the given group. */
    private function assertCanManage(int $groupId): void
    {
        abort_unless(app(SparIdentityProvider::class)->canManageGroup($groupId), 403);
    }

    public function switchGroup(int $groupId): void
    {
        // Super-admin only can switch; group-admin stays locked.
        if (app(SparIdentityProvider::class)->isSuperAdmin()) {
            $this->groupId = $groupId;
        }
    }

    public function save(): void
    {
        $this->assertCanManage((int) $this->groupId);
        $this->validate();

        $max = (int) config('spar.banners.max_per_group', 5);
        $activeCount = SparBanner::forGroup($this->groupId)->active()->count();
        if ($activeCount >= $max) {
            $this->addError('image', "This group already has the maximum of {$max} active banners. Deactivate one first.");
            return;
        }

        $path = app(SparBannerImageService::class)->store($this->image);

        $banner = SparBanner::create([
            'group_id' => $this->groupId,
            'title' => trim($this->title) ?: 'Banner',
            'image_path' => $path,
            'link_url' => trim($this->linkUrl) ?: null,
            'sort_order' => (int) (SparBanner::forGroup($this->groupId)->max('sort_order') + 1),
            'is_active' => true,
        ]);

        $this->logSparActivity('banner_created', 'Group banner uploaded', [
            'banner_id' => $banner->id, 'group_id' => $this->groupId,
        ]);

        $this->reset('image', 'title', 'linkUrl');
        $this->flash = 'Banner uploaded and converted to WebP.';
    }

    public function toggleActive(int $bannerId): void
    {
        $banner = $this->scopedBanner($bannerId);
        $banner->update(['is_active' => !$banner->is_active]);
    }

    public function moveUp(int $bannerId): void
    {
        $banner = $this->scopedBanner($bannerId);
        $above = SparBanner::forGroup($this->groupId)
            ->where('sort_order', '<', $banner->sort_order)
            ->orderByDesc('sort_order')->first();
        if ($above) {
            [$banner->sort_order, $above->sort_order] = [$above->sort_order, $banner->sort_order];
            $banner->save();
            $above->save();
        }
    }

    public function delete(int $bannerId): void
    {
        $banner = $this->scopedBanner($bannerId);
        app(SparBannerImageService::class)->delete($banner->image_path);
        $this->logSparActivity('banner_deleted', 'Group banner deleted', [
            'banner_id' => $banner->id, 'group_id' => $banner->group_id,
        ]);
        $banner->delete();
    }

    private function scopedBanner(int $id): SparBanner
    {
        $banner = SparBanner::findOrFail($id);
        $this->assertCanManage($banner->group_id);

        return $banner;
    }

    public function getBannersProperty()
    {
        return SparBanner::forGroup($this->groupId)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Banners as the PATIENT would actually see them in the mobi tracker:
     * active only, in display order. Drives the live phone preview.
     */
    public function getPreviewBannersProperty()
    {
        return SparBanner::forGroup($this->groupId)
            ->active()
            ->orderBy('sort_order')->orderBy('id')
            ->limit((int) config('spar.banners.max_per_group', 5))
            ->get();
    }

    public function getGroupsProperty()
    {
        // Super-admin may switch groups; group-admin sees only their own.
        $identity = app(SparIdentityProvider::class);
        if ($identity->isSuperAdmin()) {
            return SparPharmacyGroup::orderBy('name')->get();
        }

        return SparPharmacyGroup::whereKey($this->groupId)->get();
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-banners', [
            'width' => config('spar.banners.width', 1080),
            'height' => config('spar.banners.height', 420),
            'maxPerGroup' => config('spar.banners.max_per_group', 5),
        ])->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
