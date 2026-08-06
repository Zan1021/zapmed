<!-- Welcome & Quick Book -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Welcome back, {{ auth()->user()->first_name }}</h2>
            <p class="text-gray-500 mt-1">How are you feeling today?</p>
        </div>
        <button class="mt-4 md:mt-0 inline-flex items-center bg-zapmed-600 hover:bg-zapmed-700 text-white px-5 py-3 rounded-xl text-sm font-semibold transition-colors shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Book a Consultation
        </button>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Upcoming Appointment (highlighted) -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-blue-900 uppercase tracking-wide">Next Appointment</h3>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">In 2 days</span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-white rounded-xl shadow-sm flex items-center justify-center">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-lg font-semibold text-gray-900">Dr. Sarah Naidoo</p>
                    <p class="text-sm text-gray-600">General Consultation</p>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="font-medium">Thursday, 8 Aug</span> at <span class="font-medium">14:30</span>
                    </p>
                </div>
                <div class="flex flex-col space-y-2">
                    <button class="text-sm text-blue-700 hover:text-blue-900 font-medium">Reschedule</button>
                    <button class="text-sm text-red-600 hover:text-red-800 font-medium">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Recent Consultations -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent Consultations</h3>
            </div>
            <div class="divide-y divide-gray-50">
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-zapmed-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">General Consultation</p>
                                <p class="text-xs text-gray-500">Dr. Naidoo - 2 Aug 2026</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                            <button class="text-xs text-zapmed-600 hover:text-zapmed-800 font-medium">View Notes</button>
                        </div>
                    </div>
                </div>

                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-zapmed-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Prescription Renewal</p>
                                <p class="text-xs text-gray-500">Dr. Naidoo - 19 Jul 2026</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                            <button class="text-xs text-zapmed-600 hover:text-zapmed-800 font-medium">View Notes</button>
                        </div>
                    </div>
                </div>

                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-zapmed-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Follow-up - Blood Pressure</p>
                                <p class="text-xs text-gray-500">Dr. Patel - 5 Jul 2026</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                            <button class="text-xs text-zapmed-600 hover:text-zapmed-800 font-medium">View Notes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
        <!-- Active Prescriptions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Prescriptions</h3>
            <div class="space-y-4">
                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">Amlodipine 5mg</p>
                        <span class="text-xs text-green-600 font-medium">Active</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Once daily - 30 tablets</p>
                    <div class="flex items-center mt-2">
                        <div class="flex-1 h-1.5 bg-gray-200 rounded-full">
                            <div class="h-1.5 bg-zapmed-500 rounded-full" style="width: 40%"></div>
                        </div>
                        <span class="text-xs text-gray-500 ml-2">12 left</span>
                    </div>
                </div>

                <div class="p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">Metformin 850mg</p>
                        <span class="text-xs text-green-600 font-medium">Active</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Twice daily - 60 tablets</p>
                    <div class="flex items-center mt-2">
                        <div class="flex-1 h-1.5 bg-gray-200 rounded-full">
                            <div class="h-1.5 bg-zapmed-500 rounded-full" style="width: 65%"></div>
                        </div>
                        <span class="text-xs text-gray-500 ml-2">21 left</span>
                    </div>
                </div>

                <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">Omeprazole 20mg</p>
                        <span class="text-xs text-red-600 font-medium">Expiring</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Once daily - 30 tablets</p>
                    <div class="flex items-center mt-2">
                        <div class="flex-1 h-1.5 bg-gray-200 rounded-full">
                            <div class="h-1.5 bg-red-400 rounded-full" style="width: 10%"></div>
                        </div>
                        <span class="text-xs text-red-600 ml-2">3 left</span>
                    </div>
                    <button class="mt-2 text-xs font-medium text-red-700 hover:text-red-900">Request Renewal →</button>
                </div>
            </div>
        </div>

        <!-- Health Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Health Summary</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Blood Pressure</span>
                    <span class="text-sm font-medium text-gray-900">130/85 mmHg</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Blood Glucose</span>
                    <span class="text-sm font-medium text-gray-900">6.2 mmol/L</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Weight</span>
                    <span class="text-sm font-medium text-gray-900">78 kg</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600">Last Check-up</span>
                    <span class="text-sm font-medium text-gray-900">2 Aug 2026</span>
                </div>
            </div>
        </div>
    </div>
</div>
