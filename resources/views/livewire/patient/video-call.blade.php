<div>
    <x-slot name="header">Video Consultation</x-slot>

    @if($videoSession)
        <!-- Video Call Active -->
        <div class="max-w-5xl mx-auto">
            <!-- Call Info Bar -->
            <div class="bg-white rounded-t-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Consultation with Dr. {{ $appointment->doctor->last_name }}</p>
                        <p class="text-xs text-gray-500">{{ $appointment->reference }} &middot; {{ $appointment->appointment_date->format('j M Y') }}</p>
                    </div>
                </div>
                <button wire:click="leaveCall"
                    wire:confirm="Leave the video call?"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Leave Call
                </button>
            </div>

            <!-- Daily.co Video Embed -->
            <div class="bg-gray-900 rounded-b-xl overflow-hidden shadow-lg" style="height: 70vh;">
                <iframe
                    id="video-frame"
                    src="{{ $this->patientVideoUrl }}"
                    allow="camera; microphone; fullscreen; display-capture; autoplay"
                    style="width: 100%; height: 100%; border: 0;"
                    x-init="$wire.markJoined()">
                </iframe>
            </div>

            <!-- Tips -->
            <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-sm text-blue-700">
                    <span class="font-medium">Tips:</span> Ensure your camera and microphone are enabled. Use a well-lit area for the doctor to see you clearly. If you experience connection issues, try refreshing the page.
                </p>
            </div>
        </div>
    @else
        <!-- No Active Call -->
        <div class="max-w-md mx-auto text-center py-12">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Active Video Call</h3>
            <p class="text-sm text-gray-500 mb-6">
                Your doctor hasn't started the video call yet. You'll be able to join once they initiate the session.
            </p>
            <a href="{{ route('patient.appointments') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                &larr; Back to Appointments
            </a>
        </div>
    @endif
</div>
