<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-zapmed-600 to-zapmed-700 rounded-xl p-6 mb-8 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold">Good morning, Dr. {{ auth()->user()->last_name }}</h2>
            <p class="text-zapmed-100 mt-1">You have <span class="font-semibold text-white">6 appointments</span> scheduled today.</p>
        </div>
        <button class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Start Next Consultation
        </button>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">Today's Queue</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">6</p>
        <p class="text-xs text-green-600 mt-1">2 completed</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">Pending Prescriptions</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">3</p>
        <p class="text-xs text-amber-600 mt-1">Needs attention</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">Unread Messages</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">8</p>
        <p class="text-xs text-blue-600 mt-1">2 urgent</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">This Week</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">28</p>
        <p class="text-xs text-gray-500 mt-1">consultations</p>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Today's Schedule -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Today's Schedule</h3>
                <span class="text-sm text-gray-500">{{ now()->format('l, d M Y') }}</span>
            </div>
        </div>
        <div class="divide-y divide-gray-50">
            <!-- Current/next appointment highlighted -->
            <div class="p-4 bg-zapmed-50 border-l-4 border-zapmed-500">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-center min-w-[50px]">
                            <span class="text-xs text-gray-500">10:00</span>
                            <span class="text-xs text-gray-400">30min</span>
                        </div>
                        <div class="w-10 h-10 bg-zapmed-200 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-zapmed-800">LV</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Lerato Van der Merwe</p>
                            <p class="text-xs text-gray-600">Follow-up - Hypertension</p>
                        </div>
                    </div>
                    <button class="bg-zapmed-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-zapmed-700 transition-colors">
                        Join Call
                    </button>
                </div>
            </div>

            <div class="p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-center min-w-[50px]">
                            <span class="text-xs text-gray-500">10:30</span>
                            <span class="text-xs text-gray-400">20min</span>
                        </div>
                        <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-amber-700">SN</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Sipho Ndlovu</p>
                            <p class="text-xs text-gray-500">Prescription Renewal - Chronic Meds</p>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400">In 30 min</span>
                </div>
            </div>

            <div class="p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-center min-w-[50px]">
                            <span class="text-xs text-gray-500">11:00</span>
                            <span class="text-xs text-gray-400">30min</span>
                        </div>
                        <div class="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-rose-700">NM</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Naledi Mahlangu</p>
                            <p class="text-xs text-gray-500">New Patient - General Consultation</p>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400">In 1h</span>
                </div>
            </div>

            <div class="p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-center min-w-[50px]">
                            <span class="text-xs text-gray-500">14:00</span>
                            <span class="text-xs text-gray-400">30min</span>
                        </div>
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-indigo-700">AP</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Aisha Patel</p>
                            <p class="text-xs text-gray-500">Follow-up - Lab Results Review</p>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400">In 4h</span>
                </div>
            </div>

            <div class="p-4 hover:bg-gray-50 transition-colors opacity-60">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-center min-w-[50px]">
                            <span class="text-xs text-gray-500">09:00</span>
                            <span class="text-xs text-gray-400">30min</span>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium text-blue-700">TM</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 line-through">Thabo Mokoena</p>
                            <p class="text-xs text-gray-500">General Consultation</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Done</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
        <!-- Pending Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Pending Actions</h3>
            <div class="space-y-3">
                <div class="flex items-start space-x-3 p-3 bg-amber-50 rounded-lg">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Prescription awaiting signature</p>
                        <p class="text-xs text-gray-500">Sipho Ndlovu - 2 items</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Sick note request</p>
                        <p class="text-xs text-gray-500">Thabo Mokoena - from today's consult</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-purple-50 rounded-lg">
                    <svg class="w-5 h-5 text-purple-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Lab results to review</p>
                        <p class="text-xs text-gray-500">Aisha Patel - blood panel</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Messages -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Messages</h3>
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-medium text-blue-700">TM</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Thabo Mokoena</p>
                        <p class="text-xs text-gray-500 truncate">Thank you doctor, feeling much better since...</p>
                        <p class="text-xs text-gray-400 mt-1">2 min ago</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-medium text-rose-700">NM</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">Naledi Mahlangu</p>
                        <p class="text-xs text-gray-500 truncate">Hi Dr, I wanted to ask about the side effects...</p>
                        <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
