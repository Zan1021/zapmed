<div>
    <a href="{{ route('admin.users') }}" class="text-sm text-indigo-600 hover:underline">&larr; All users</a>
    <div class="mb-6 mt-2">
        <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
        <p class="text-sm text-gray-500">{{ $user->email }} &middot; now: {{ $user->role->label() }}</p>
    </div>

    @if($saved)<p class="mb-3 rounded-lg bg-green-50 p-3 text-sm text-green-800" data-saved>Saved.</p>@endif
    @foreach(['role', 'user', 'registration_number', 'registration_status', 'country', 'booking_url', 'authorisation_booking_url', 'signature'] as $f)
        @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror
    @endforeach

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="role">
        <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Role</h2>
        <select wire:model="role" class="rounded-lg border-gray-300 text-sm">
            @foreach($roles as $r)<option value="{{ $r }}">{{ \App\Enums\UserRole::from($r)->label() }}</option>@endforeach
            @unless(in_array($user->role->value, $roles))<option value="{{ $user->role->value }}">{{ $user->role->label() }}</option>@endunless
        </select>
        <button type="button" wire:click="assignRole" data-action="assign-role" class="ml-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Change role</button>
    </div>

    @if($isStaff)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="profile">
            <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Staff profile</h2>
            <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <label>Registration number <input type="text" wire:model="registrationNumber" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                <label>Board <input type="text" wire:model="board" list="boards" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <datalist id="boards">@foreach($boards as $b)<option value="{{ $b }}">@endforeach</datalist></label>
                <label>Registration status
                    <select wire:model="registrationStatus" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @foreach($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select></label>
                <label>Country (two letters) <input type="text" wire:model="country" maxlength="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase"></label>
                <label class="md:col-span-2">Practice address <textarea wire:model="practiceAddress" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea></label>
                <label>Booking link <input type="url" wire:model="bookingUrl" placeholder="https://" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                <label>Authorisation check-in link <input type="url" wire:model="authorisationBookingUrl" placeholder="https://" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            </div>
            @if($registrationStatus === 'verified' && $isDoctor)<p class="mt-2 text-xs text-gray-500">A verified doctor can be booked by patients.</p>@endif
            <button type="button" wire:click="save" data-action="save-profile" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Save profile</button>
        </div>

        @if($isDoctor)
            <div class="rounded-xl border border-gray-200 bg-white p-5" data-section="signature">
                <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Signature for prescriptions</h2>
                @if($profile?->signature_path)<p class="mb-2 text-sm"><a class="text-indigo-600 hover:underline" href="{{ route('admin.staff-signature', $user) }}">See the current signature</a></p>@endif
                <input type="file" wire:model="signature" class="text-sm">
                <button type="button" wire:click="uploadSignature" data-action="upload-signature" class="ml-2 rounded-lg border border-gray-300 px-3 py-2 text-sm">Upload</button>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500" data-not-staff>Only doctors, health coaches and Operations users have a staff profile. Change their role above to give them one.</p>
    @endif
</div>
