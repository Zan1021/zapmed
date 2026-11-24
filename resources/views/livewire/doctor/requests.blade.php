<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Doctor Requests</h1>
            <p class="text-sm text-gray-500 mt-1">Work passed between doctors, Operations and coaches.</p>
        </div>
        @if($canCreate && ! $showForm)
            <button type="button" wire:click="newRequest" data-action="new-request"
                class="px-4 py-2 bg-zapmed-600 text-white text-sm font-medium rounded-lg hover:bg-zapmed-700">New request</button>
        @endif
    </div>

    @if($showForm)
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6" data-form="new-request">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">New request</h2>

            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" wire:model="title" maxlength="160" class="w-full text-sm border-gray-300 rounded-lg mb-1">
            @error('title') <p class="text-xs text-red-600 mb-2">{{ $message }}</p> @enderror

            <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">What is needed</label>
            <textarea wire:model="description" rows="4" class="w-full text-sm border-gray-300 rounded-lg"></textarea>

            <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">Doctor(s) to do it</label>
            <div class="flex flex-wrap gap-3">
                @foreach($doctors as $d)
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="assigneeIds" value="{{ $d->id }}" class="rounded border-gray-300 mr-2">
                        {{ $d->first_name }} {{ $d->last_name }}
                    </label>
                @endforeach
            </div>
            @error('assignee_ids') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

            <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">Keep in the loop (optional)</label>
            <div class="flex flex-wrap gap-3">
                @foreach($staff as $s)
                    <label class="inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="participantIds" value="{{ $s->id }}" class="rounded border-gray-300 mr-2">
                        {{ $s->first_name }} {{ $s->last_name }} <span class="text-xs text-gray-400 ml-1">({{ $s->role->label() }})</span>
                    </label>
                @endforeach
            </div>
            @error('participant_ids') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

            <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">Patients this is about (optional)</label>
            <input type="text" wire:model.live.debounce.300ms="patientSearch" placeholder="Search by name, email or member number"
                class="w-full text-sm border-gray-300 rounded-lg">
            @if($choices->isNotEmpty())
                <div class="border border-gray-200 rounded-lg mt-1 divide-y divide-gray-100">
                    @foreach($choices as $c)
                        <button type="button" wire:click="addPatient({{ $c->id }})" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                            {{ $c->first_name }} {{ $c->last_name }} <span class="text-xs text-gray-400">{{ $c->member_number }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
            @foreach($refs as $i => $ref)
                <div class="flex items-center gap-3 mt-2 text-sm" data-ref="{{ $ref['patient_id'] }}">
                    <span class="font-medium text-gray-800">{{ $patientNames[$ref['patient_id']]->name ?? 'Patient' }}</span>
                    <select wire:model="refs.{{ $i }}.order_id" class="text-sm border-gray-300 rounded-lg">
                        <option value="">No specific order</option>
                        @foreach($ordersByPatient[$ref['patient_id']] ?? [] as $o)
                            <option value="{{ $o->id }}">{{ $o->reference }} ({{ $o->status }})</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="removeRef({{ $i }})" class="text-xs text-red-600">Remove</button>
                </div>
            @endforeach
            @error('refs') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

            <div class="mt-5 flex gap-3">
                <button type="button" wire:click="create" data-action="send-request"
                    class="px-4 py-2 bg-zapmed-600 text-white text-sm font-medium rounded-lg hover:bg-zapmed-700">Send request</button>
                <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['open' => 'Open', 'mine' => 'Asked by me', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-lg border {{ $filter === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Request</th>
                    <th class="px-5 py-3 text-left">From</th>
                    <th class="px-5 py-3 text-left">To</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Sent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $r)
                    <tr class="hover:bg-gray-50" data-request="{{ $r->id }}">
                        <td class="px-5 py-4">
                            <a href="{{ route('doctor.requests.show', $r) }}" class="font-medium text-zapmed-700 hover:underline">{{ $r->title }}</a>
                            @if($r->refs->isNotEmpty())
                                <p class="text-xs text-gray-400">{{ $r->refs->count() }} {{ \Illuminate\Support\Str::plural('patient', $r->refs->count()) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-700">{{ $r->requester?->name ?? 'Unknown' }}</td>
                        <td class="px-5 py-4 text-gray-700">{{ $r->assignees->map->name->join(', ') }}</td>
                        <td class="px-5 py-4 text-center"><span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ $r->label() }}</span></td>
                        <td class="px-5 py-4 text-right text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400" data-empty="requests">No requests here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
