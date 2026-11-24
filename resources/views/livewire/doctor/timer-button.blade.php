<div class="flex items-center gap-3" data-timer="{{ $isRunning ? 'running' : 'stopped' }}">
    <button type="button" wire:click="toggle"
        class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $isRunning ? 'bg-red-50 text-red-700 border-red-200' : 'bg-white text-gray-700 border-gray-300' }}">
        {{ $isRunning ? 'Stop timer' : 'Start timer' }}
    </button>
    @if($isRunning && $since)
        <span class="text-xs text-gray-500">running since {{ $since->setTimezone(config('doctor.time.timezone'))->format('H:i') }}</span>
    @endif
    <a href="{{ route('doctor.time') }}" class="text-xs text-zapmed-700 hover:underline">Time sheet</a>
</div>
