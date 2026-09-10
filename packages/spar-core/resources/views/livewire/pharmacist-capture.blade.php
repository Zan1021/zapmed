<div class="max-w-4xl mx-auto py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Capture patient details</h1>
            <p class="text-sm text-gray-500">Patients imported without contact details. Capture their name and
                contact to onboard them and send their medication tracker.</p>
        </div>
    </div>

    @if($flash)
        <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4">
            <p class="text-sm text-green-800">{{ $flash }}</p>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3">Profile Code</th>
                    <th class="px-4 py-3">Medical Aid</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($patients as $patient)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $patient->profile_code }}
                            @unless($patient->is_primary_member)
                                <span class="text-xs text-gray-400">(dependant)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $patient->medical_aid_name ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700">
                                Awaiting contact
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $patient->id }})"
                                    class="text-green-700 font-medium hover:text-green-800">Capture</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                            No patients awaiting contact capture. 🎉
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $patients->links() }}</div>
    </div>

    {{-- Capture modal --}}
    @if($editingId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Capture contact details</h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">First name</label>
                        <input type="text" wire:model="firstName" class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                        @error('firstName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Surname</label>
                        <input type="text" wire:model="lastName" class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                        @error('lastName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Cellphone</label>
                        <input type="text" wire:model="cellphone" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="0821234567" />
                        @error('cellphone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Email</label>
                        <input type="email" wire:model="email" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="name@example.co.za" />
                        @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-gray-400">At least one of cellphone or email is required.</p>

                    <label class="flex items-start gap-2 pt-2 cursor-pointer">
                        <input type="checkbox" wire:model="consentConfirmed" class="mt-1 rounded border-gray-300 text-green-600" />
                        <span class="text-sm text-gray-700">
                            The patient has agreed in-store to receive medication reminders and to
                            {{ config('spar.branding.name', 'SPAR Pharmacy') }} processing their information.
                            (They will confirm again on their phone.)
                        </span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button wire:click="cancel" class="text-gray-500 text-sm">Cancel</button>
                    <button wire:click="save"
                            class="bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg px-4 py-2">
                        Save &amp; send link
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
