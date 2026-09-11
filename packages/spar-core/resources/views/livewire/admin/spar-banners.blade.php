<div>
    <x-slot name="header">Promo Banners</x-slot>

    @if($flash)
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-green-800">{{ $flash }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ============ LEFT: management ============ --}}
        <div class="lg:col-span-2 space-y-8">
    {{-- Group switcher (super-admin) --}}
    @if($this->groups->count() > 1)
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
            <select wire:change="switchGroup($event.target.value)" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                @foreach($this->groups as $g)
                    <option value="{{ $g->id }}" @selected($g->id === $groupId)>{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Upload --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Add a banner</h3>
        <p class="text-sm text-gray-500 mb-4">
            Recommended size <strong>{{ $width }}×{{ $height }}px</strong> (landscape). Any JPG/PNG/WebP —
            it's automatically resized and converted to a compact WebP for mobile. Max {{ $maxPerGroup }} active
            banners. Shown on the patient app after they consent.
        </p>

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image <span class="text-red-500">*</span></label>
                    <input type="file" wire:model="image" accept=".jpg,.jpeg,.png,.webp"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100" />
                    @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    @if($image)
                        <div class="mt-2">
                            <p class="text-xs text-gray-400 mb-1">Preview:</p>
                            <img src="{{ $image->temporaryUrl() }}" class="rounded-lg border border-gray-100 max-h-32" />
                        </div>
                    @endif
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title (internal)</label>
                        <input type="text" wire:model="title" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Winter flu specials" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Link URL (optional)</label>
                        <input type="url" wire:model="linkUrl" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="https://…" />
                        @error('linkUrl') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="image,save"
                    class="px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="save,image">Upload banner</span>
                <span wire:loading wire:target="save">Converting…</span>
                <span wire:loading wire:target="image">Uploading…</span>
            </button>
        </form>
    </div>

    {{-- Existing banners --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Current banners ({{ $this->banners->count() }})</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($this->banners as $banner)
                <div class="p-4 flex items-center gap-4">
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="w-40 h-16 object-cover rounded-lg border border-gray-100" />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900">{{ $banner->title }}</p>
                        @if($banner->link_url)
                            <p class="text-xs text-blue-600 truncate">{{ $banner->link_url }}</p>
                        @else
                            <p class="text-xs text-gray-400">No link</p>
                        @endif
                        <p class="text-xs text-gray-500 mt-1">
                            {{ number_format($banner->impressions) }} views ·
                            {{ number_format($banner->clicks) }} clicks ·
                            {{ $banner->click_through_rate }}% CTR
                        </p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $banner->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                        {{ $banner->is_active ? 'Active' : 'Hidden' }}
                    </span>
                    <div class="flex items-center gap-2 text-xs">
                        <button wire:click="moveUp({{ $banner->id }})" class="text-gray-500 hover:text-gray-700">↑</button>
                        <button wire:click="toggleActive({{ $banner->id }})" class="text-blue-600 hover:text-blue-800">
                            {{ $banner->is_active ? 'Hide' : 'Show' }}
                        </button>
                        <button wire:click="delete({{ $banner->id }})" wire:confirm="Delete this banner?" class="text-red-600 hover:text-red-800">Delete</button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">No banners yet. Upload one above.</div>
            @endforelse
        </div>
    </div>
        </div>{{-- /left column --}}

        {{-- ============ RIGHT: live mobi preview ============ --}}
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-6">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Mobi preview</h3>
                    <span class="text-xs text-gray-400">what patients see</span>
                </div>

                {{-- Phone frame --}}
                <div class="mx-auto w-full max-w-[320px] rounded-[2.5rem] border-[10px] border-gray-900 bg-gray-900 shadow-xl">
                    <div class="overflow-hidden rounded-[1.8rem] bg-gray-50">
                        {{-- notch --}}
                        <div class="flex items-center justify-center bg-gray-900 py-1.5">
                            <div class="h-1.5 w-16 rounded-full bg-gray-700"></div>
                        </div>

                        {{-- app chrome: logo --}}
                        <div class="flex items-center justify-center border-b border-gray-100 bg-white py-3">
                            <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}"
                                 alt="{{ config('spar.branding.name', 'Pharmacy at SPAR') }}" class="h-8 w-auto" />
                        </div>

                        <div class="p-3">
                            {{-- Banner carousel — mirrors the real tracker (1080x420, auto-rotate). --}}
                            @php
                                $pendingUrl = $image ? $image->temporaryUrl() : null;
                                $previewBanners = $this->previewBanners;
                                $slideCount = ($pendingUrl ? 1 : 0) + $previewBanners->count();
                            @endphp

                            @if($slideCount > 0)
                                <div x-data="{ i: 0, n: {{ $slideCount }} }"
                                     x-init="if (n > 1) setInterval(() => i = (i + 1) % n, 3000)"
                                     wire:key="preview-{{ $slideCount }}-{{ $pendingUrl ? 'p' : 'n' }}">
                                    <div class="relative overflow-hidden rounded-2xl bg-gray-100" style="aspect-ratio: 1080 / 420;">
                                        @php $slide = 0; @endphp
                                        @if($pendingUrl)
                                            <div x-show="i === {{ $slide }}" x-transition.opacity class="absolute inset-0">
                                                <img src="{{ $pendingUrl }}" alt="New banner preview" class="h-full w-full object-cover" />
                                                <span class="absolute left-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow">New — not saved</span>
                                            </div>
                                            @php $slide++; @endphp
                                        @endif
                                        @foreach($previewBanners as $banner)
                                            <div x-show="i === {{ $slide }}" x-transition.opacity class="absolute inset-0">
                                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="h-full w-full object-cover" />
                                            </div>
                                            @php $slide++; @endphp
                                        @endforeach

                                        @if($slideCount > 1)
                                            <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                                                @for($d = 0; $d < $slideCount; $d++)
                                                    <button type="button" @click="i = {{ $d }}" class="h-1.5 w-1.5 rounded-full"
                                                            :class="i === {{ $d }} ? 'bg-white' : 'bg-white/50'"></button>
                                                @endfor
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-white text-center"
                                     style="aspect-ratio: 1080 / 420;">
                                    <p class="px-4 text-xs text-gray-400">No active banners. Upload one, or click “Show” on a hidden banner — it appears here instantly.</p>
                                </div>
                            @endif

                            {{-- faux content below the slider so it reads like the tracker --}}
                            <div class="mt-3 space-y-2">
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="h-2.5 w-24 rounded bg-gray-200"></div>
                                    <div class="mt-2 h-2 w-32 rounded bg-gray-100"></div>
                                </div>
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="h-2.5 w-20 rounded bg-gray-200"></div>
                                    <div class="mt-2 h-2 w-28 rounded bg-gray-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-gray-400">
                    Shows active banners in order. A pending upload previews first, tagged “New”.
                </p>
            </div>
        </div>
    </div>{{-- /grid --}}
</div>
