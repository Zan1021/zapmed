<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" data-panel="authorisation">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Authorisation</h3>

    @foreach($current as $a)
        <p class="text-xs text-gray-600 mb-1" data-current-authorisation="{{ $a->id }}">Order {{ $a->order?->reference }}: authorised until {{ $a->expires_on->format('j M Y') }}.</p>
    @endforeach

    @if($orders->isNotEmpty())
        <label class="block text-xs text-gray-500 mt-2 mb-1">Authorise repeats for {{ $months }} months</label>
        <select wire:model="orderId" class="w-full text-sm border-gray-300 rounded-lg mb-2">
            <option value="">Choose an order...</option>
            @foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->reference }} ({{ $o->status }})</option>@endforeach
        </select>
        @error('order') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
        <button type="button" wire:click="authorise" data-action="authorise" class="px-3 py-1.5 bg-zapmed-600 text-white text-xs font-medium rounded-lg">Authorise</button>
    @endif
    <a href="{{ route('doctor.authorisations') }}" class="block mt-3 text-xs text-zapmed-700 hover:underline">All authorisations</a>
</div>
