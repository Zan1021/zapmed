<div>
    <div class="max-w-5xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">My Progress</h1>
            <p class="text-sm text-gray-500 mt-1">Track your health journey daily. Small steps lead to big results.</p>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $this->stats['current_weight'] ? number_format($this->stats['current_weight'], 1) . ' kg' : '—' }}</p>
                <p class="text-xs text-gray-500 mt-1">Current Weight</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                @if($this->stats['weight_change_30d'] !== null)
                    <p class="text-2xl font-bold {{ $this->stats['weight_change_30d'] < 0 ? 'text-green-600' : ($this->stats['weight_change_30d'] > 0 ? 'text-red-500' : 'text-gray-900') }}">
                        {{ $this->stats['weight_change_30d'] > 0 ? '+' : '' }}{{ $this->stats['weight_change_30d'] }} kg
                    </p>
                @else
                    <p class="text-2xl font-bold text-gray-900">—</p>
                @endif
                <p class="text-xs text-gray-500 mt-1">30-Day Change</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-zapmed-600">{{ $this->stats['streak_days'] }}</p>
                <p class="text-xs text-gray-500 mt-1">Day Streak</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $this->stats['total_logs'] }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Entries</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Daily Log Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Daily Log</h2>
                        <input type="date" wire:model.live="logDate" class="text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                    </div>

                    @if($logSaved)
                        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded-lg text-sm">
                            Saved!
                        </div>
                    @endif

                    <div class="space-y-6">
                        <!-- Weight & Measurements -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Measurements</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500">Weight (kg)</label>
                                    <input type="number" wire:model="weight" step="0.1" placeholder="e.g. 82.5" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Waist (cm)</label>
                                    <input type="number" wire:model="waist" step="0.1" placeholder="e.g. 88" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                            </div>
                        </div>

                        <!-- Wellness -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Wellness (1-10)</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500">Energy</label>
                                    <input type="number" wire:model="energyLevel" min="1" max="10" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Mood</label>
                                    <input type="number" wire:model="mood" min="1" max="10" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Sleep (hrs)</label>
                                    <input type="number" wire:model="sleepHours" min="0" max="24" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Water (glasses)</label>
                                    <input type="number" wire:model="waterGlasses" min="0" max="20" class="mt-1 w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                </div>
                            </div>
                        </div>

                        <!-- Medication & Exercise -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Activity</h3>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model="medicationTaken" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                                    <span class="text-sm text-gray-700">Took my medication today</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="exercised" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                                    <span class="text-sm text-gray-700">I exercised today</span>
                                </label>
                                @if($exercised)
                                    <div class="grid grid-cols-2 gap-4 pl-8">
                                        <div>
                                            <input type="text" wire:model="exerciseType" placeholder="Type (e.g. walking, gym)" class="w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                        </div>
                                        <div>
                                            <input type="number" wire:model="exerciseMinutes" placeholder="Minutes" class="w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Notes</h3>
                            <textarea wire:model="mealsSummary" rows="2" placeholder="What did you eat today? (brief)" class="w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500 mb-3"></textarea>
                            <textarea wire:model="symptoms" rows="2" placeholder="Any symptoms or side effects?" class="w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500 mb-3"></textarea>
                            <textarea wire:model="notes" rows="2" placeholder="General notes..." class="w-full text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500"></textarea>
                        </div>

                        <!-- Save Button -->
                        <button wire:click="saveLog" class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors">
                            Save Today's Log
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Goals -->
            <div>
                <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">My Goals</h2>
                        <button wire:click="$toggle('showGoalForm')" class="text-xs text-zapmed-600 hover:text-zapmed-700 font-medium">
                            + Add Goal
                        </button>
                    </div>

                    @if($showGoalForm)
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg space-y-3">
                            <select wire:model="goalType" class="w-full text-sm border-gray-300 rounded-lg">
                                <option value="weight">Target Weight (kg)</option>
                                <option value="waist">Target Waist (cm)</option>
                                <option value="exercise">Exercise (min/day)</option>
                                <option value="water">Water (glasses/day)</option>
                                <option value="sleep">Sleep (hours/night)</option>
                            </select>
                            <input type="number" wire:model="goalTarget" step="0.1" placeholder="Target value" class="w-full text-sm border-gray-300 rounded-lg">
                            <input type="date" wire:model="goalDate" class="w-full text-sm border-gray-300 rounded-lg">
                            <button wire:click="addGoal" class="w-full bg-zapmed-600 text-white py-2 rounded-lg text-sm font-medium">Set Goal</button>
                        </div>
                    @endif

                    <div class="space-y-3">
                        @forelse($this->goals as $goal)
                            <div class="p-3 border border-gray-100 rounded-lg {{ $goal->achieved ? 'bg-green-50 border-green-200' : '' }}">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ ucfirst($goal->type) }}: {{ number_format($goal->target_value, 1) }} {{ $goal->unit }}
                                        </p>
                                        @if($goal->target_date)
                                            <p class="text-xs text-gray-500">By {{ $goal->target_date->format('d M Y') }}</p>
                                        @endif
                                    </div>
                                    @if($goal->achieved)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium">Achieved!</span>
                                    @else
                                        <button wire:click="deleteGoal({{ $goal->id }})" class="text-xs text-gray-400 hover:text-red-500">Remove</button>
                                    @endif
                                </div>
                                @if($goal->progress_percent !== null && !$goal->achieved)
                                    <div class="mt-2 bg-gray-100 rounded-full h-2 overflow-hidden">
                                        <div class="bg-zapmed-500 h-full rounded-full" style="width: {{ min($goal->progress_percent, 100) }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-4">No goals set yet.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Weight Chart (simple — rendered with data for Alpine/Chart.js) -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-900">Weight Trend</h2>
                        <select wire:model.live="period" class="text-xs border-gray-300 rounded-lg">
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>

                    @if(count($this->chartData['weights']) > 1)
                        <div class="h-40" x-data="weightChart(@js($this->chartData))" x-init="init()">
                            <canvas x-ref="canvas" class="w-full h-full"></canvas>
                        </div>
                    @else
                        <div class="h-40 flex items-center justify-center">
                            <p class="text-sm text-gray-400">Log at least 2 weights to see your trend.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        function weightChart(data) {
            return {
                init() {
                    new Chart(this.$refs.canvas, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.weights,
                                borderColor: '#64cc0f',
                                backgroundColor: 'rgba(100, 204, 15, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#64cc0f',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                                y: { ticks: { font: { size: 10 } } }
                            }
                        }
                    });
                }
            }
        }
    </script>
    @endpush
</div>
