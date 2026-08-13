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

        <!-- More Calculators Toggle -->
        <div class="text-center mt-8" x-data="{ showMore: false }">
            <button @click="showMore = !showMore" class="inline-flex items-center px-6 py-3 bg-white border border-zapmed-600 text-zapmed-600 hover:bg-zapmed-50 rounded-xl text-sm font-semibold transition-colors">
                <span x-text="showMore ? 'Show Less' : 'More Calculators'"></span>
                <svg class="w-4 h-4 ml-2 transition-transform" :class="showMore ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="showMore" x-collapse x-cloak>

        <!-- Row 2: ED Score + Hair Loss Risk -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">

            <!-- ED Score (IIEF-5) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="edScoreCalculator()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">ED Score (IIEF-5)</h3>
                <p class="text-sm text-gray-500 mb-6">A clinically validated 5-question assessment for erectile function.</p>

                <div class="space-y-4" x-show="!showResult">
                    <template x-for="(q, i) in questions" :key="i">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2" x-text="q.text"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(opt, oi) in q.options" :key="oi">
                                    <button @click="answers[i] = opt.score; checkComplete()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                                        :class="answers[i] === opt.score ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-600 border-gray-200 hover:border-zapmed-300'">
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <button @click="calculateScore()" x-show="allAnswered" x-transition class="w-full mt-4 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors">
                        See My Score
                    </button>
                </div>

                <div x-show="showResult" x-transition class="text-center">
                    <div class="mb-4">
                        <span class="text-5xl font-bold" :class="scoreColor" x-text="totalScore"></span>
                        <span class="text-lg text-gray-400">/25</span>
                    </div>
                    <p class="text-sm font-semibold" :class="scoreColor" x-text="severity"></p>
                    <div class="mt-4 p-4 rounded-lg" :class="scoreBg">
                        <p class="text-sm" :class="scoreTextColor" x-text="scoreInsight"></p>
                    </div>
                    <a href="{{ route('patient.book', ['category' => 'mens-health', 'treatment' => 'erectile-dysfunction-treatment']) }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Get Discreet ED Treatment
                    </a>
                    <button @click="showResult = false; answers = [null,null,null,null,null]" class="mt-2 text-xs text-gray-500 hover:text-gray-700">Retake</button>
                </div>
            </div>

            <!-- Hair Loss Risk -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="hairLossRisk()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Hair Loss Risk Score</h3>
                <p class="text-sm text-gray-500 mb-6">5 quick questions to understand your hair loss risk.</p>

                <div class="space-y-4" x-show="!showResult">
                    <template x-for="(q, i) in questions" :key="i">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2" x-text="q.text"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(opt, oi) in q.options" :key="oi">
                                    <button @click="answers[i] = opt.score; checkComplete()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                                        :class="answers[i] === opt.score ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-600 border-gray-200 hover:border-zapmed-300'">
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <button @click="calculateScore()" x-show="allAnswered" x-transition class="w-full mt-4 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors">
                        See My Risk
                    </button>
                </div>

                <div x-show="showResult" x-transition class="text-center">
                    <div class="mb-4">
                        <span class="text-5xl font-bold" :class="riskColor" x-text="riskLevel"></span>
                    </div>
                    <div class="mt-4 p-4 rounded-lg" :class="riskBg">
                        <p class="text-sm" :class="riskTextColor" x-text="riskInsight"></p>
                    </div>
                    <a href="{{ route('patient.book', ['category' => 'general-health', 'treatment' => 'hair-loss-treatment']) }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Start Hair Loss Treatment
                    </a>
                    <button @click="showResult = false; answers = [null,null,null,null,null]" class="mt-2 text-xs text-gray-500 hover:text-gray-700">Retake</button>
                </div>
            </div>

        </div>

        <!-- Row 3: Skin Age + Savings Calculator -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">

            <!-- Skin Age Calculator -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="skinAgeCalculator()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Skin Age Calculator</h3>
                <p class="text-sm text-gray-500 mb-6">Find out if your skin is aging faster than it should.</p>

                <div class="space-y-4" x-show="!showResult">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Your actual age</label>
                        <input type="number" x-model="age" placeholder="35" min="18" max="80" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <template x-for="(q, i) in questions" :key="i">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2" x-text="q.text"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(opt, oi) in q.options" :key="oi">
                                    <button @click="answers[i] = opt.score"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                                        :class="answers[i] === opt.score ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-600 border-gray-200 hover:border-zapmed-300'">
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <button @click="calculateSkinAge()" class="w-full mt-4 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors">
                        Calculate My Skin Age
                    </button>
                </div>

                <div x-show="showResult" x-transition class="text-center">
                    <p class="text-sm text-gray-500">Your estimated skin age is</p>
                    <span class="text-5xl font-bold" :class="skinAge > age ? 'text-red-600' : 'text-green-600'" x-text="skinAge"></span>
                    <p class="text-sm mt-1" :class="skinAge > age ? 'text-red-500' : 'text-green-500'" x-text="skinAge > age ? (skinAge - age) + ' years older than your actual age' : (age - skinAge) + ' years younger than your actual age'"></p>
                    <div class="mt-4 p-4 rounded-lg" :class="skinAge > age ? 'bg-red-50' : 'bg-green-50'">
                        <p class="text-sm" :class="skinAge > age ? 'text-red-800' : 'text-green-800'" x-text="skinInsight"></p>
                    </div>
                    <a href="{{ route('patient.book', ['category' => 'skincare', 'treatment' => 'anti-aging-treatment']) }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Explore Anti-Aging Treatments
                    </a>
                    <button @click="showResult = false; answers = [null,null,null,null,null]" class="mt-2 text-xs text-gray-500 hover:text-gray-700">Retake</button>
                </div>
            </div>

            <!-- Savings Calculator -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="savingsCalculator()">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Cost Savings Calculator</h3>
                <p class="text-sm text-gray-500 mb-6">See how much you save vs. traditional GP visits.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">How often do you see a GP per year?</label>
                        <select x-model="visitsPerYear" @change="calculate()" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="2">2 visits</option>
                            <option value="4">4 visits</option>
                            <option value="6">6 visits</option>
                            <option value="12">12 visits (monthly)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">What do you pay per GP visit?</label>
                        <select x-model="gpCost" @change="calculate()" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="45000">R450 (average)</option>
                            <option value="55000">R550</option>
                            <option value="65000">R650</option>
                            <option value="80000">R800+</option>
                        </select>
                    </div>
                </div>

                <div x-show="showResults" x-transition class="mt-6">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-red-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-red-600 font-medium">Traditional GP</p>
                            <p class="text-2xl font-bold text-red-700" x-text="'R' + gpTotal"></p>
                            <p class="text-xs text-red-500">per year</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-xs text-green-600 font-medium">With Zapmed</p>
                            <p class="text-2xl font-bold text-green-700" x-text="'R' + zapmedTotal"></p>
                            <p class="text-xs text-green-500">per year</p>
                        </div>
                    </div>
                    <div class="bg-zapmed-50 rounded-lg p-4 text-center border border-zapmed-100">
                        <p class="text-sm text-zapmed-700">You save <span class="text-xl font-bold" x-text="'R' + savings"></span> per year</p>
                        <p class="text-xs text-zapmed-500 mt-1">Plus no travel time, no waiting rooms, and medication delivered</p>
                    </div>
                    <a href="{{ route('register') }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Start Saving Today
                    </a>
                </div>
            </div>

        </div>

            </div><!-- end x-collapse -->
        </div><!-- end showMore x-data -->

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

function edScoreCalculator() {
    return {
        answers: [null, null, null, null, null],
        allAnswered: false,
        showResult: false,
        totalScore: 0,
        severity: '',
        scoreColor: '',
        scoreBg: '',
        scoreTextColor: '',
        scoreInsight: '',
        questions: [
            { text: 'How confident are you that you can get an erection?', options: [{label:'Very low',score:1},{label:'Low',score:2},{label:'Moderate',score:3},{label:'High',score:4},{label:'Very high',score:5}] },
            { text: 'When you had erections, how often were they hard enough for penetration?', options: [{label:'Almost never',score:1},{label:'Few times',score:2},{label:'Sometimes',score:3},{label:'Most times',score:4},{label:'Almost always',score:5}] },
            { text: 'During intercourse, how often could you maintain your erection?', options: [{label:'Almost never',score:1},{label:'Few times',score:2},{label:'Sometimes',score:3},{label:'Most times',score:4},{label:'Almost always',score:5}] },
            { text: 'How difficult was it to maintain your erection to completion?', options: [{label:'Extremely',score:1},{label:'Very',score:2},{label:'Difficult',score:3},{label:'Slightly',score:4},{label:'Not difficult',score:5}] },
            { text: 'When you attempted intercourse, how often was it satisfactory?', options: [{label:'Almost never',score:1},{label:'Few times',score:2},{label:'Sometimes',score:3},{label:'Most times',score:4},{label:'Almost always',score:5}] },
        ],
        checkComplete() { this.allAnswered = this.answers.every(a => a !== null); },
        calculateScore() {
            this.totalScore = this.answers.reduce((a, b) => a + b, 0);
            if (this.totalScore >= 22) { this.severity = 'No ED'; this.scoreColor = 'text-green-600'; this.scoreBg = 'bg-green-50'; this.scoreTextColor = 'text-green-800'; this.scoreInsight = 'Your score suggests normal erectile function. If you still have concerns, a consultation can provide peace of mind.'; }
            else if (this.totalScore >= 17) { this.severity = 'Mild ED'; this.scoreColor = 'text-amber-600'; this.scoreBg = 'bg-amber-50'; this.scoreTextColor = 'text-amber-800'; this.scoreInsight = 'You may have mild ED. Treatment options are very effective at this stage — most men see significant improvement with medication.'; }
            else if (this.totalScore >= 12) { this.severity = 'Mild to Moderate ED'; this.scoreColor = 'text-orange-600'; this.scoreBg = 'bg-orange-50'; this.scoreTextColor = 'text-orange-800'; this.scoreInsight = 'Your score suggests mild-to-moderate ED. A doctor can prescribe effective treatment. Most men respond well to first-line medications.'; }
            else if (this.totalScore >= 8) { this.severity = 'Moderate ED'; this.scoreColor = 'text-red-500'; this.scoreBg = 'bg-red-50'; this.scoreTextColor = 'text-red-800'; this.scoreInsight = 'Moderate ED is very treatable. A doctor consultation will help identify the right medication and dose for you.'; }
            else { this.severity = 'Severe ED'; this.scoreColor = 'text-red-700'; this.scoreBg = 'bg-red-50'; this.scoreTextColor = 'text-red-800'; this.scoreInsight = 'Your score suggests severe ED. This is manageable with medical help. A doctor can explore the best treatment approach for your situation.'; }
            this.showResult = true;
        }
    }
}

function hairLossRisk() {
    return {
        answers: [null, null, null, null, null],
        allAnswered: false,
        showResult: false,
        riskLevel: '',
        riskColor: '',
        riskBg: '',
        riskTextColor: '',
        riskInsight: '',
        questions: [
            { text: 'Family history of hair loss (father or grandfather)?', options: [{label:'Yes — significant',score:3},{label:'Yes — some',score:2},{label:'No',score:0}] },
            { text: 'Have you noticed thinning or a receding hairline?', options: [{label:'Yes — noticeable',score:3},{label:'Slightly',score:1},{label:'No',score:0}] },
            { text: 'How long has this been happening?', options: [{label:'3+ years',score:3},{label:'1-3 years',score:2},{label:'Less than 1 year',score:1},{label:'Not happening',score:0}] },
            { text: 'Do you find excess hair on your pillow or in the shower?', options: [{label:'Often',score:2},{label:'Sometimes',score:1},{label:'No',score:0}] },
            { text: 'Your age?', options: [{label:'Under 25',score:1},{label:'25-35',score:2},{label:'35-50',score:2},{label:'50+',score:1}] },
        ],
        checkComplete() { this.allAnswered = this.answers.every(a => a !== null); },
        calculateScore() {
            const total = this.answers.reduce((a, b) => a + b, 0);
            if (total <= 3) { this.riskLevel = 'Low Risk'; this.riskColor = 'text-green-600'; this.riskBg = 'bg-green-50'; this.riskTextColor = 'text-green-800'; this.riskInsight = 'Your risk factors are low. Keep monitoring. If you notice changes, early treatment is most effective.'; }
            else if (total <= 7) { this.riskLevel = 'Moderate Risk'; this.riskColor = 'text-amber-600'; this.riskBg = 'bg-amber-50'; this.riskTextColor = 'text-amber-800'; this.riskInsight = 'You have moderate risk factors for hair loss. Early intervention with finasteride or minoxidil can slow or reverse thinning.'; }
            else { this.riskLevel = 'High Risk'; this.riskColor = 'text-red-600'; this.riskBg = 'bg-red-50'; this.riskTextColor = 'text-red-800'; this.riskInsight = 'Your profile suggests high risk of progressive hair loss. Medical treatment is most effective when started early. A doctor can help.'; }
            this.showResult = true;
        }
    }
}

function skinAgeCalculator() {
    return {
        age: '', answers: [null, null, null, null, null], showResult: false, skinAge: 0, skinInsight: '',
        questions: [
            { text: 'Sun exposure without SPF?', options: [{label:'Daily',score:5},{label:'Often',score:3},{label:'Sometimes',score:1},{label:'Rarely/never',score:0}] },
            { text: 'Do you smoke?', options: [{label:'Yes',score:4},{label:'Used to',score:2},{label:'No',score:0}] },
            { text: 'How much water do you drink daily?', options: [{label:'Less than 4 glasses',score:3},{label:'4-6 glasses',score:1},{label:'7+ glasses',score:0}] },
            { text: 'Do you use retinol or prescription skincare?', options: [{label:'Yes, consistently',score:-3},{label:'Sometimes',score:-1},{label:'No',score:2}] },
            { text: 'How many hours of sleep per night?', options: [{label:'Less than 5',score:3},{label:'5-6',score:1},{label:'7+',score:0}] },
        ],
        calculateSkinAge() {
            if (!this.age || this.age < 18) return;
            const modifier = this.answers.filter(a => a !== null).reduce((a, b) => a + b, 0);
            this.skinAge = Math.round(parseInt(this.age) + modifier);
            this.skinInsight = this.skinAge > this.age
                ? 'Your lifestyle factors are accelerating skin aging. Retinoids, SPF, and proper hydration can help reverse the damage.'
                : 'Great news — your habits are keeping your skin younger. Keep it up with consistent skincare and sun protection.';
            this.showResult = true;
        }
    }
}

function savingsCalculator() {
    return {
        visitsPerYear: '4', gpCost: '45000', showResults: true, gpTotal: '0', zapmedTotal: '0', savings: '0',
        init() { this.calculate(); },
        calculate() {
            const visits = parseInt(this.visitsPerYear);
            const cost = parseInt(this.gpCost);
            const gpYear = (visits * cost) / 100;
            const zapYear = (220 * 12); // R220/month subscription
            this.gpTotal = gpYear.toLocaleString();
            this.zapmedTotal = zapYear.toLocaleString();
            this.savings = Math.max(0, gpYear - zapYear).toLocaleString();
            this.showResults = true;
        }
    }
}
</script>
