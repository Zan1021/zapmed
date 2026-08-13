<!-- Welcome & Quick Book -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Welcome back, {{ auth()->user()->first_name }}</h2>
            <p class="text-gray-500 mt-1">Member: <span class="font-medium text-gray-700">{{ auth()->user()->member_number }}</span></p>
        </div>
        <a href="{{ route('patient.book') }}" class="mt-4 md:mt-0 inline-flex items-center bg-zapmed-600 hover:bg-zapmed-700 text-white px-5 py-3 rounded-xl text-sm font-semibold transition-colors shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Book a Consultation
        </a>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Upcoming Appointment -->
        @php
            $nextAppointment = auth()->user()->appointments()
                ->where('scheduled_at', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('scheduled_at')
                ->with('doctor.user')
                ->first();
        @endphp

        @if($nextAppointment)
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-blue-900 uppercase tracking-wide">Next Appointment</h3>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $nextAppointment->scheduled_at->diffForHumans() }}
                </span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-white rounded-xl shadow-sm flex items-center justify-center">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-lg font-semibold text-gray-900">Dr. {{ $nextAppointment->doctor->user->last_name }}</p>
                    <p class="text-sm text-gray-600">{{ $nextAppointment->type ?? 'General Consultation' }}</p>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="font-medium">{{ $nextAppointment->scheduled_at->format('l, j M') }}</span> at <span class="font-medium">{{ $nextAppointment->scheduled_at->format('H:i') }}</span>
                    </p>
                </div>
            </div>
        </div>
        @else
        <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-xl border border-gray-200 p-6">
            <div class="text-center py-4">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="text-sm font-semibold text-gray-700">No Upcoming Appointments</h3>
                <p class="text-sm text-gray-500 mt-1">Book a consultation to get started with your treatment.</p>
                <a href="{{ route('patient.book') }}" class="mt-4 inline-flex items-center text-sm font-medium text-zapmed-600 hover:text-zapmed-700">
                    Book Now
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        @endif

        <!-- Recent Consultations -->
        @php
            $recentAppointments = auth()->user()->appointments()
                ->where('status', 'completed')
                ->orderByDesc('scheduled_at')
                ->with('doctor.user')
                ->limit(5)
                ->get();
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent Consultations</h3>
            </div>
            @if($recentAppointments->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach($recentAppointments as $appointment)
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-zapmed-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $appointment->type ?? 'General Consultation' }}</p>
                                <p class="text-xs text-gray-500">Dr. {{ $appointment->doctor->user->last_name }} - {{ $appointment->scheduled_at->format('j M Y') }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center">
                <p class="text-sm text-gray-500">No consultations yet. Book your first appointment to get started.</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
        <!-- Active Prescriptions -->
        @php
            $prescriptions = auth()->user()->prescriptions()
                ->where('status', 'active')
                ->latest()
                ->limit(5)
                ->get();
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Prescriptions</h3>
            @if($prescriptions->count() > 0)
            <div class="space-y-4">
                @foreach($prescriptions as $prescription)
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">{{ $prescription->medication_name ?? 'Prescription' }}</p>
                        <span class="text-xs text-green-600 font-medium">Active</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Prescribed {{ $prescription->created_at->format('j M Y') }}</p>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-4">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
                <p class="text-sm text-gray-500">No active prescriptions.</p>
            </div>
            @endif
        </div>

        <!-- Health Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Profile</h3>
            <div class="space-y-3">
                @if(auth()->user()->weight_kg)
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Weight</span>
                    <span class="text-sm font-medium text-gray-900">{{ auth()->user()->patientProfile->weight_kg ?? '-' }} kg</span>
                </div>
                @endif
                @if(auth()->user()->patientProfile?->height_cm)
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Height</span>
                    <span class="text-sm font-medium text-gray-900">{{ auth()->user()->patientProfile->height_cm }} cm</span>
                </div>
                @endif
                @if(auth()->user()->patientProfile?->blood_type)
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Blood Type</span>
                    <span class="text-sm font-medium text-gray-900">{{ auth()->user()->patientProfile->blood_type }}</span>
                </div>
                @endif
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600">Member Since</span>
                    <span class="text-sm font-medium text-gray-900">{{ auth()->user()->created_at->format('M Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
