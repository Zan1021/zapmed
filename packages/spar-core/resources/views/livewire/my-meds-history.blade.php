<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900">Collection History</h2>
        <a href="{{ route('my-meds.track') }}" class="text-sm text-green-600 font-medium hover:text-green-700">
            &larr; Back
        </a>
    </div>

    @if($this->history->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-gray-500">No collection history yet.</p>
            <p class="text-sm text-gray-400 mt-1">Your past medication collections will appear here.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->history as $record)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $record->status === 'collected' ? 'bg-green-100' : 'bg-blue-100' }}">
                                @if($record->status === 'collected')
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Dispense #{{ $record->dispense_number }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($record->status) }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400">{{ $record->completed_at?->format('d M Y') }}</p>
                    </div>

                    @if($record->journey?->pharmacy)
                        <p class="text-xs text-gray-500 flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $record->journey->pharmacy->name }}
                        </p>
                    @endif

                    @if($record->items && count($record->items) > 0)
                        <div class="mt-2 pt-2 border-t border-gray-50">
                            @foreach($record->items as $item)
                                <p class="text-xs text-gray-600">{{ $item['name'] ?? 'Medication' }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if($record->sales_value)
                        <p class="text-xs text-gray-400 mt-1">Value: {{ $record->formatted_sales_value }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
