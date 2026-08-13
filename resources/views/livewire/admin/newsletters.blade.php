<div>
    <x-slot name="header">Newsletter Manager</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Newsletters</h2>
            <p class="text-sm text-gray-500">{{ $subscriberCount }} active subscribers</p>
        </div>
        <button wire:click="compose" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
            + Compose Newsletter
        </button>
    </div>

    <!-- Compose Form -->
    @if($composing)
    <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit' : 'New' }} Newsletter</h3>
        <div class="space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-600">Subject Line *</label>
                <input wire:model="subject" type="text" placeholder="e.g. Your weight loss journey starts here" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                @error('subject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Segment</label>
                <select wire:model="segment" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @foreach($segments as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Body (HTML supported) *</label>
                <textarea wire:model="body" rows="12" placeholder="Write your newsletter content here. HTML is supported." class="w-full mt-1 rounded-lg border-gray-300 text-sm font-mono focus:border-zapmed-500 focus:ring-zapmed-500"></textarea>
                @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex gap-3 mt-4">
            <button wire:click="saveDraft" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg">Save Draft</button>
            <button wire:click="$set('composing', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
        </div>
    </div>
    @endif

    <!-- Newsletter List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Subject</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Segment</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Sent</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Opens</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($newsletters as $nl)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ $nl->subject }}</p>
                        <p class="text-xs text-gray-400">{{ $nl->created_at->format('j M Y H:i') }}</p>
                    </td>
                    <td class="px-4 py-3 text-center text-xs">{{ ucfirst(str_replace('-', ' ', $nl->segment)) }}</td>
                    <td class="px-4 py-3 text-center">{{ $nl->sent_count }}/{{ $nl->recipients_count }}</td>
                    <td class="px-4 py-3 text-center">{{ $nl->open_rate }}%</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $nl->status === 'sent' ? 'bg-green-100 text-green-700' : ($nl->status === 'sending' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ucfirst($nl->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($nl->status === 'draft')
                        <button wire:click="edit({{ $nl->id }})" class="text-xs text-zapmed-600 hover:text-zapmed-700 font-medium mr-2">Edit</button>
                        <button wire:click="send({{ $nl->id }})" wire:confirm="Send this newsletter to all matching subscribers?" class="text-xs text-green-600 hover:text-green-700 font-medium mr-2">Send</button>
                        <button wire:click="delete({{ $nl->id }})" wire:confirm="Delete this draft?" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No newsletters yet. Compose your first one!</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
