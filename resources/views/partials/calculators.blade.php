<!-- Calculators Section -->
<section id="calculators" class="py-20 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Health Calculators</h2>
            <p class="mt-4 text-lg text-gray-500">Understand your body. Set your goals. Start your journey.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- BMI Calculator -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="bmiCalculator()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">BMI Calculator</h3>
                <p class="text-sm text-gray-500 mb-6">Calculate your Body Mass Index to understand your weight category.</p>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                        <input type="number" x-model="height" @input="calculate()" placeholder="170" min="100" max="250"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                        <input type="number" x-model="weight" @input="calculate()" placeholder="75" min="30" max="300"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                </div>

                <button @click="calculate()" class="w-full mt-4 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    Calculate My BMI
                </button>

                <!-- Result -->
                <div x-show="bmi > 0" x-transition class="mt-6">
                    <!-- BMI Value -->
                    <div class="text-center mb-4">
                        <span class="text-5xl font-bold" :class="categoryColor" x-text="bmi.toFixed(1)"></span>
                        <p class="text-sm font-medium mt-1" :class="categoryColor" x-text="category"></p>
                    </div>

                    <!-- Visual Gauge -->
                    <div class="relative h-4 rounded-full overflow-hidden bg-gray-100 mb-3">
                        <div class="absolute inset-0 flex">
                            <div class="w-1/4 bg-blue-400"></div>
                            <div class="w-1/4 bg-green-400"></div>
                            <div class="w-1/4 bg-amber-400"></div>
                            <div class="w-1/4 bg-red-400"></div>
                        </div>
                        <div class="absolute top-0 bottom-0 w-1 bg-gray-900 rounded transition-all duration-500 shadow-lg"
                             :style="'left: ' + gaugePosition + '%'"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mb-4">
                        <span>Underweight</span>
                        <span>Normal</span>
                        <span>Overweight</span>
                        <span>Obese</span>
                    </div>

                    <!-- Health Insight -->
                    <div class="p-4 rounded-lg" :class="insightBg">
                        <p class="text-sm" :class="insightText" x-text="insight"></p>
                    </div>

                    <!-- Healthy Weight Range -->
                    <div class="mt-4 text-center">
                        <p class="text-xs text-gray-500">Healthy weight for your height: <span class="font-semibold text-gray-700" x-text="healthyRange"></span></p>
                    </div>
                </div>

                <div x-show="bmi === 0" class="mt-6 text-center py-8">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <p class="text-sm text-gray-400">Enter your height and weight to see your BMI</p>
                </div>
            </div>

            <!-- Weight Loss Projector -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="weightLossCalculator()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Weight Loss Projector</h3>
                <p class="text-sm text-gray-500 mb-6">See how much you could lose with doctor-guided GLP-1 treatment.</p>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Weight (kg)</label>
                        <input type="number" x-model="currentWeight" @input="project()" placeholder="95" min="50" max="300"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Goal Weight (kg)</label>
                        <input type="number" x-model="goalWeight" @input="project()" placeholder="75" min="40" max="250"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                </div>

                <!-- Results -->
                <div x-show="showResults" x-transition class="mt-6">
                    <!-- Progress Cards -->
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="bg-zapmed-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-zapmed-600 font-medium">3 Months</p>
                            <p class="text-2xl font-bold text-zapmed-700" x-text="month3 + ' kg'"></p>
                            <p class="text-xs text-zapmed-500" x-text="'-' + loss3 + ' kg'"></p>
                        </div>
                        <div class="bg-zapmed-100 rounded-lg p-3 text-center">
                            <p class="text-xs text-zapmed-600 font-medium">6 Months</p>
                            <p class="text-2xl font-bold text-zapmed-700" x-text="month6 + ' kg'"></p>
                            <p class="text-xs text-zapmed-500" x-text="'-' + loss6 + ' kg'"></p>
                        </div>
                        <div class="bg-zapmed-200 rounded-lg p-3 text-center">
                            <p class="text-xs text-zapmed-700 font-medium">12 Months</p>
                            <p class="text-2xl font-bold text-zapmed-800" x-text="month12 + ' kg'"></p>
                            <p class="text-xs text-zapmed-600" x-text="'-' + loss12 + ' kg'"></p>
                        </div>
                    </div>

                    <!-- Visual Progress Bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span x-text="'Start: ' + currentWeight + ' kg'"></span>
                            <span x-text="'Goal: ' + goalWeight + ' kg'"></span>
                        </div>
                        <div class="h-5 bg-gray-100 rounded-full overflow-hidden relative">
                            <div class="h-full bg-gradient-to-r from-zapmed-400 to-zapmed-600 rounded-full transition-all duration-1000"
                                 :style="'width: ' + progressPercent + '%'"></div>
                        </div>
                        <p class="text-xs text-gray-500 text-center mt-1" x-text="percentLoss + '% of excess weight projected in 12 months'"></p>
                    </div>

                    <!-- Insight -->
                    <div class="p-4 bg-zapmed-50 rounded-lg border border-zapmed-100">
                        <p class="text-sm text-zapmed-800"><strong>Clinical data:</strong> Patients on GLP-1 medication typically lose 15-20% of body weight over 12 months with medical supervision and lifestyle changes.</p>
                    </div>

                    <!-- CTA -->
                    <a href="{{ route('register') }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Start Your Weight Loss Journey
                    </a>
                </div>

                <div x-show="!showResults" class="mt-6 text-center py-8">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <p class="text-sm text-gray-400">Enter your current and goal weight to see projections</p>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
function bmiCalculator() {
    return {
        height: '',
        weight: '',
        bmi: 0,
        category: '',
        categoryColor: '',
        gaugePosition: 0,
        insight: '',
        insightBg: '',
        insightText: '',
        healthyRange: '',

        calculate() {
            if (!this.height || !this.weight || this.height < 100 || this.weight < 30) {
                this.bmi = 0;
                return;
            }

            const h = this.height / 100;
            this.bmi = this.weight / (h * h);

            // Category
            if (this.bmi < 18.5) {
                this.category = 'Underweight';
                this.categoryColor = 'text-blue-600';
                this.insight = 'Your BMI suggests you are underweight. Consider consulting a doctor about healthy weight gain strategies.';
                this.insightBg = 'bg-blue-50';
                this.insightText = 'text-blue-800';
            } else if (this.bmi < 25) {
                this.category = 'Normal Weight';
                this.categoryColor = 'text-green-600';
                this.insight = 'Your BMI is in the healthy range. Maintain your current lifestyle with regular exercise and balanced nutrition.';
                this.insightBg = 'bg-green-50';
                this.insightText = 'text-green-800';
            } else if (this.bmi < 30) {
                this.category = 'Overweight';
                this.categoryColor = 'text-amber-600';
                this.insight = 'Your BMI indicates you are overweight. A doctor-guided weight management programme could help you reach a healthier weight.';
                this.insightBg = 'bg-amber-50';
                this.insightText = 'text-amber-800';
            } else {
                this.category = 'Obese';
                this.categoryColor = 'text-red-600';
                this.insight = 'Your BMI indicates obesity. Medical weight-loss treatment such as GLP-1 medication may be clinically appropriate. Speak to a doctor.';
                this.insightBg = 'bg-red-50';
                this.insightText = 'text-red-800';
            }

            // Gauge position (map BMI 15-40 to 0-100%)
            this.gaugePosition = Math.min(Math.max(((this.bmi - 15) / 25) * 100, 2), 98);

            // Healthy weight range
            const minW = (18.5 * h * h).toFixed(0);
            const maxW = (24.9 * h * h).toFixed(0);
            this.healthyRange = minW + ' - ' + maxW + ' kg';
        }
    }
}

function weightLossCalculator() {
    return {
        currentWeight: '',
        goalWeight: '',
        showResults: false,
        month3: 0,
        month6: 0,
        month12: 0,
        loss3: 0,
        loss6: 0,
        loss12: 0,
        progressPercent: 0,
        percentLoss: 0,

        project() {
            if (!this.currentWeight || !this.goalWeight || this.currentWeight <= this.goalWeight || this.currentWeight < 50) {
                this.showResults = false;
                return;
            }

            this.showResults = true;
            const cw = parseFloat(this.currentWeight);
            const gw = parseFloat(this.goalWeight);

            // GLP-1 typical loss: ~5% at 3mo, ~10% at 6mo, ~15-20% at 12mo
            const loss3Raw = cw * 0.05;
            const loss6Raw = cw * 0.10;
            const loss12Raw = cw * 0.17;

            // Cap at goal weight
            this.loss3 = Math.min(loss3Raw, cw - gw).toFixed(1);
            this.loss6 = Math.min(loss6Raw, cw - gw).toFixed(1);
            this.loss12 = Math.min(loss12Raw, cw - gw).toFixed(1);

            this.month3 = (cw - this.loss3).toFixed(1);
            this.month6 = (cw - this.loss6).toFixed(1);
            this.month12 = (cw - this.loss12).toFixed(1);

            // Progress toward goal
            const totalToLose = cw - gw;
            this.progressPercent = Math.min((this.loss12 / totalToLose) * 100, 100).toFixed(0);
            this.percentLoss = ((this.loss12 / cw) * 100).toFixed(0);
        }
    }
}
</script>
