<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Operations reports</h1>
        <p class="mt-1 text-sm text-gray-500">Read-only. No clinical notes or results appear here. Money reconciliation is on the <a class="text-zapmed-700 hover:underline" href="{{ route('admin.finance') }}">Finance</a> page.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm">
        <label>Report
            <select wire:model.live="report" class="mt-1 block rounded-lg border-gray-300 text-sm" data-field="report">
                @foreach($reportNames as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </label>
        <label>Period
            <select wire:model.live="useMonth" class="mt-1 block rounded-lg border-gray-300 text-sm">
                <option value="1">A month</option>
                <option value="0">Between two dates</option>
            </select>
        </label>
        @if($useMonth === '1')
            <label>Month <input type="month" wire:model.live="month" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        @else
            <label>From <input type="date" wire:model.live="from" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
            <label>To <input type="date" wire:model.live="to" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        @endif
        @if($report === 'user_medication')
            <label>Product
                <select wire:model.live="productId" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    <option value="">All products</option>
                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </label>
            <label>Patients
                <select wire:model.live="state" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    <option value="all">All</option><option value="active">Active</option><option value="expired">Expired</option>
                </select>
            </label>
        @endif
        @if($report === 'customer_tracker')
            <label>Status <input type="text" wire:model.live.debounce.400ms="status" placeholder="e.g. Paused" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        @endif
        <a href="{{ $csvUrl }}" data-action="export" class="ml-auto rounded-lg border border-gray-300 px-3 py-2">Download CSV</a>
    </div>

    @foreach(['report', 'month', 'from', 'to', 'state'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    @if($result)
        @if($result['summary'])
            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4" data-section="summary">
                @foreach($result['summary'] as $label => $value)
                    <div class="rounded-xl border border-gray-200 bg-white p-3"><p class="text-xs text-gray-500">{{ $label }}</p><p class="text-lg font-semibold text-gray-900">{{ $value }}</p></div>
                @endforeach
            </div>
        @endif
        @if($result['note'])<p class="mb-3 text-xs text-gray-500">{{ $result['note'] }}</p>@endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm" data-report="{{ $result['key'] }}">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>@foreach($result['columns'] as $c)<th class="px-4 py-3 text-left">{{ $c }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($result['rows'] as $row)
                        <tr>@foreach($row as $cell)<td class="px-4 py-2 text-gray-800">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($result['columns']) }}" class="px-4 py-10 text-center text-gray-400" data-empty="report">Nothing in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
