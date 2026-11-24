<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" data-panel="blood-tests">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Blood tests</h3>
    @if($open > 0)
        <p class="text-xs text-gray-600 mb-2" data-open-bloodtests>{{ $open }} open {{ \Illuminate\Support\Str::plural('request', $open) }}. <a class="text-zapmed-700 hover:underline" href="{{ route('doctor.blood-tests') }}">See them</a></p>
    @endif

    <div class="space-y-1 mb-2">
        @foreach($catalogue as $key => $label)
            <label class="flex items-center text-xs text-gray-700"><input type="checkbox" wire:model="tests" value="{{ $key }}" class="rounded border-gray-300 mr-2">{{ $label }}</label>
        @endforeach
    </div>
    @error('tests') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror

    <label class="block text-xs text-gray-500 mb-1">Anything else</label>
    <textarea wire:model="freeText" rows="2" class="w-full text-sm border-gray-300 rounded-lg mb-2"></textarea>

    <label class="block text-xs text-gray-500 mb-1">Lab form</label>
    <select wire:model="labForm" class="w-full text-sm border-gray-300 rounded-lg mb-1">
        <option value="">None chosen</option>
        @foreach($forms as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
    </select>
    <input type="file" wire:model="labFormFile" class="text-xs mb-2">
    @error('lab_form') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
    @error('lab_form_file') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror

    <label class="block text-xs text-gray-500 mb-1">Order (optional)</label>
    <select wire:model="orderId" class="w-full text-sm border-gray-300 rounded-lg mb-2">
        <option value="">No order</option>
        @foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->reference }} ({{ $o->status }})</option>@endforeach
    </select>
    @error('order') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror

    <button type="button" wire:click="request" data-action="request-bloodtests" class="px-3 py-1.5 bg-zapmed-600 text-white text-xs font-medium rounded-lg">Request blood tests</button>
</div>
