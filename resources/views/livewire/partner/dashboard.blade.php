<div>
    <x-slot name="header">Partner Dashboard</x-slot>

    <!-- Welcome -->
    <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-xl p-6 mb-8 text-white">
        <h2 class="text-2xl font-bold">{{ $partner->name }}</h2>
        <p class="text-emerald-100 mt-1">Partner ID: <span class="font-mono font-semibold text-white">{{ $partner->slug }}</span></p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">This Month Referrals</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $monthReferrals }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Conversions (all time)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $conversions }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $conversionRate }}% rate</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Pending Earnings</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ currency($pendingEarnings) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Total Earned</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ currency($totalEarnings) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Embed Code Generator -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Your Embed Code</h3>

                <!-- Simple link -->
                <div class="mb-4">
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Referral Link</label>
                    <div class="flex">
                        <input type="text" readonly value="{{ url('/register?ref=' . $partner->slug) }}"
                            class="flex-1 rounded-l-lg border-gray-300 text-xs font-mono bg-gray-50">
                        <button onclick="navigator.clipboard.writeText('{{ url('/register?ref=' . $partner->slug) }}')"
                            class="px-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-xs font-medium text-gray-600 hover:bg-gray-200">
                            Copy
                        </button>
                    </div>
                </div>

                <!-- Script embed -->
                <div class="mb-4">
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Website Embed (any site)</label>
                    <textarea readonly rows="3"
                        class="w-full rounded-lg border-gray-300 text-xs font-mono bg-gray-50">&lt;script src="{{ url('/embed.js') }}" data-partner="{{ $partner->slug }}" data-type="floating"&gt;&lt;/script&gt;</textarea>
                </div>

                <!-- Card embed -->
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Card Widget</label>
                    <textarea readonly rows="4"
                        class="w-full rounded-lg border-gray-300 text-xs font-mono bg-gray-50">&lt;div id="zapmed-widget"&gt;&lt;/div&gt;
&lt;script src="{{ url('/embed.js') }}" data-partner="{{ $partner->slug }}" data-type="card"&gt;&lt;/script&gt;</textarea>
                </div>
            </div>

            <!-- Commission Rates -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Your Rates</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Consultation</span>
                        <span class="font-semibold text-gray-900">{{ $partner->commission_consultation }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Medication</span>
                        <span class="font-semibold text-gray-900">{{ $partner->commission_medication }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Cookie Duration</span>
                        <span class="font-semibold text-gray-900">{{ $partner->cookie_days }} days</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Recent Commissions -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Commissions</h3>
                </div>
                @if($recentCommissions->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-sm text-gray-500">No commissions yet. Share your embed code to start earning!</p>
                </div>
                @else
                <div class="divide-y divide-gray-50">
                    @foreach($recentCommissions as $commission)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ ucfirst($commission->type) }}</p>
                            <p class="text-xs text-gray-500">{{ $commission->reference }} &middot; {{ $commission->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $commission->formatted_amount }}</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $commission->status === 'paid' ? 'bg-green-100 text-green-700' : ($commission->status === 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ ucfirst($commission->status) }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
