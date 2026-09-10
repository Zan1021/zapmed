<div>
     <?php $__env->slot('header', null, []); ?> SPAR Reporting & Analytics <?php $__env->endSlot(); ?>

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-green-700 via-green-800 to-emerald-900 rounded-xl p-6 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Analytics & Reporting</h2>
                <p class="text-green-200 mt-1">Performance metrics across all SPAR pharmacies.</p>
            </div>
            <div class="flex items-center gap-3">
                <select wire:model.live="period" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white focus:ring-white/50 focus:border-white/50 [&>option]:text-gray-900">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 6 months</option>
                    <option value="365">Last year</option>
                </select>
                <select wire:model.live="pharmacyFilter" class="bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white focus:ring-white/50 focus:border-white/50 [&>option]:text-gray-900">
                    <option value="">All Pharmacies</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->pharmacies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pharmacy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($pharmacy->id); ?>"><?php echo e($pharmacy->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Hero KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Adherence Rate -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-green-50 to-transparent rounded-bl-full"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Adherence</span>
                </div>
                <p class="text-4xl font-black <?php echo e($this->adherenceStats['adherence_rate'] >= 80 ? 'text-green-600' : ($this->adherenceStats['adherence_rate'] >= 60 ? 'text-amber-600' : 'text-red-600')); ?>">
                    <?php echo e($this->adherenceStats['adherence_rate']); ?><span class="text-xl">%</span>
                </p>
                <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 <?php echo e($this->adherenceStats['adherence_rate'] >= 80 ? 'bg-green-500' : ($this->adherenceStats['adherence_rate'] >= 60 ? 'bg-amber-500' : 'bg-red-500')); ?>" style="width: <?php echo e($this->adherenceStats['adherence_rate']); ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2"><?php echo e($this->adherenceStats['on_time']); ?> of <?php echo e($this->adherenceStats['total_due']); ?> on time</p>
            </div>
        </div>

        <!-- Renewal Rate -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-purple-50 to-transparent rounded-bl-full"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Renewal Rate</span>
                </div>
                <p class="text-4xl font-black text-purple-600"><?php echo e($this->renewalStats['renewal_rate']); ?><span class="text-xl">%</span></p>
                <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-500 rounded-full transition-all duration-700" style="width: <?php echo e($this->renewalStats['renewal_rate']); ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2"><?php echo e($this->renewalStats['renewed']); ?> renewed, <?php echo e($this->renewalStats['renewal_due']); ?> pending</p>
            </div>
        </div>

        <!-- Opt-in Rate -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-blue-50 to-transparent rounded-bl-full"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Opt-in Rate</span>
                </div>
                <p class="text-4xl font-black text-blue-600"><?php echo e($this->patientStats['opt_in_rate']); ?><span class="text-xl">%</span></p>
                <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full transition-all duration-700" style="width: <?php echo e($this->patientStats['opt_in_rate']); ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2"><?php echo e($this->patientStats['consented']); ?> of <?php echo e($this->patientStats['total']); ?> patients</p>
            </div>
        </div>

        <!-- ZapMed Conversion -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-emerald-50 to-transparent rounded-bl-full"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">ZapMed Consults</span>
                </div>
                <p class="text-4xl font-black text-emerald-600"><?php echo e($this->renewalStats['zapmed_conversion']); ?><span class="text-xl">%</span></p>
                <div class="flex items-center gap-4 mt-3">
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-xs text-gray-500">ZapMed: <?php echo e($this->renewalStats['via_zapmed']); ?></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                        <span class="text-xs text-gray-500">GP: <?php echo e($this->renewalStats['via_primary_doctor']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Monthly Adherence Chart (Bar Chart) -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900">Monthly Adherence Trend</h3>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded bg-green-500"></div>
                        <span class="text-xs text-gray-500">Completed</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded bg-gray-200"></div>
                        <span class="text-xs text-gray-500">Due</span>
                    </div>
                </div>
            </div>
            <!-- Bar Chart via Alpine -->
            <div class="flex items-end gap-4 h-48" x-data="{ shown: false }" x-intersect="shown = true">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->monthlyTrend; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex-1 flex flex-col items-center gap-2">
                        <div class="w-full flex flex-col items-center justify-end h-40 relative">
                            <!-- Background bar (total due) -->
                            <div class="w-full bg-gray-100 rounded-t-lg absolute bottom-0 transition-all duration-700"
                                 :style="shown ? 'height: <?php echo e($month['due'] > 0 ? min(100, ($month['due'] / max(1, collect($this->monthlyTrend)->max('due'))) * 100) : 0); ?>%' : 'height: 0%'">
                            </div>
                            <!-- Foreground bar (completed) -->
                            <div class="w-3/4 rounded-t-lg absolute bottom-0 transition-all duration-1000 delay-300 <?php echo e($month['rate'] >= 80 ? 'bg-green-500' : ($month['rate'] >= 60 ? 'bg-amber-500' : 'bg-red-500')); ?>"
                                 :style="shown ? 'height: <?php echo e($month['due'] > 0 ? min(100, ($month['completed'] / max(1, collect($this->monthlyTrend)->max('due'))) * 100) : 0); ?>%' : 'height: 0%'">
                            </div>
                        </div>
                        <span class="text-xs text-gray-500 font-medium"><?php echo e(Str::before($month['label'], ' ')); ?></span>
                        <span class="text-xs font-bold <?php echo e($month['rate'] >= 80 ? 'text-green-600' : ($month['rate'] >= 60 ? 'text-amber-600' : 'text-red-600')); ?>"><?php echo e($month['rate']); ?>%</span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Donut Chart: Renewal Route Split -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Renewal Route</h3>
            <?php
                $zapmedPct = $this->renewalStats['zapmed_conversion'];
                $gpPct = 100 - $zapmedPct;
                $circumference = 2 * 3.14159 * 45;
                $zapmedDash = ($zapmedPct / 100) * $circumference;
                $gpDash = ($gpPct / 100) * $circumference;
            ?>
            <div class="flex items-center justify-center mb-6">
                <div class="relative" x-data="{ shown: false }" x-intersect="shown = true">
                    <svg class="w-40 h-40 transform -rotate-90" viewBox="0 0 100 100">
                        <!-- Background circle -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#f3f4f6" stroke-width="10"/>
                        <!-- GP segment -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#d1d5db" stroke-width="10"
                                :stroke-dasharray="shown ? '<?php echo e($gpDash); ?> <?php echo e($circumference); ?>' : '0 <?php echo e($circumference); ?>'"
                                stroke-dashoffset="0"
                                class="transition-all duration-1000"/>
                        <!-- ZapMed segment -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#10b981" stroke-width="10"
                                :stroke-dasharray="shown ? '<?php echo e($zapmedDash); ?> <?php echo e($circumference); ?>' : '0 <?php echo e($circumference); ?>'"
                                stroke-dashoffset="-<?php echo e($gpDash); ?>"
                                class="transition-all duration-1000 delay-500"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="text-center">
                            <p class="text-2xl font-black text-gray-900"><?php echo e($this->renewalStats['renewed'] + $this->renewalStats['renewal_due']); ?></p>
                            <p class="text-xs text-gray-500">Total</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                        <span class="text-sm text-gray-600">ZapMed Consultation</span>
                    </div>
                    <span class="text-sm font-bold text-gray-900"><?php echo e($this->renewalStats['via_zapmed']); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span class="text-sm text-gray-600">Primary Doctor</span>
                    </div>
                    <span class="text-sm font-bold text-gray-900"><?php echo e($this->renewalStats['via_primary_doctor']); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                        <span class="text-sm text-gray-600">Pending Renewal</span>
                    </div>
                    <span class="text-sm font-bold text-gray-900"><?php echo e($this->renewalStats['renewal_due']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders & Patients Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Order Breakdown -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Order Fulfillment</h3>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-gradient-to-br from-green-50 to-green-100/50 rounded-xl p-4 text-center border border-green-100">
                    <div class="w-10 h-10 bg-green-200/50 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                    </div>
                    <p class="text-3xl font-black text-green-700"><?php echo e($this->orderStats['collections']); ?></p>
                    <p class="text-xs text-green-600 font-medium mt-1">Collections (<?php echo e($this->orderStats['collection_pct']); ?>%)</p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl p-4 text-center border border-blue-100">
                    <div class="w-10 h-10 bg-blue-200/50 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                    </div>
                    <p class="text-3xl font-black text-blue-700"><?php echo e($this->orderStats['deliveries']); ?></p>
                    <p class="text-xs text-blue-600 font-medium mt-1">Deliveries (<?php echo e(100 - $this->orderStats['collection_pct']); ?>%)</p>
                </div>
            </div>
            <div class="space-y-3 border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Total Orders</span>
                    <span class="text-sm font-bold text-gray-900"><?php echo e($this->orderStats['total']); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Completed</span>
                    <span class="text-sm font-bold text-green-600"><?php echo e($this->orderStats['completed']); ?></span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->orderStats['avg_prep_minutes']): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Avg Preparation Time</span>
                        <span class="text-sm font-bold text-gray-900"><?php echo e($this->orderStats['avg_prep_minutes']); ?> min</span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Patient Overview - Visual -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Patient Overview</h3>
            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="text-center p-3 rounded-xl bg-gray-50">
                    <p class="text-3xl font-black text-gray-900"><?php echo e(number_format($this->patientStats['total'])); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Total</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-green-50">
                    <p class="text-3xl font-black text-green-700"><?php echo e(number_format($this->patientStats['active'])); ?></p>
                    <p class="text-xs text-green-600 mt-1">Active</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-purple-50">
                    <p class="text-3xl font-black text-purple-700">+<?php echo e($this->patientStats['new_this_period']); ?></p>
                    <p class="text-xs text-purple-600 mt-1">New</p>
                </div>
            </div>
            <!-- Consent Breakdown Bar -->
            <div class="mb-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Consent Status</span>
                </div>
                <?php
                    $total = max(1, $this->patientStats['total']);
                    $optInW = ($this->patientStats['consented'] / $total) * 100;
                    $optOutW = ($this->patientStats['opted_out'] / $total) * 100;
                    $pendingW = 100 - $optInW - $optOutW;
                ?>
                <div class="h-6 bg-gray-100 rounded-full overflow-hidden flex">
                    <div class="bg-green-500 h-full transition-all duration-700 flex items-center justify-center" style="width: <?php echo e($optInW); ?>%">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($optInW > 10): ?> <span class="text-[10px] font-bold text-white"><?php echo e(round($optInW)); ?>%</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="bg-gray-300 h-full transition-all duration-700 flex items-center justify-center" style="width: <?php echo e($pendingW); ?>%">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingW > 10): ?> <span class="text-[10px] font-bold text-gray-600"><?php echo e(round($pendingW)); ?>%</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="bg-red-400 h-full transition-all duration-700 flex items-center justify-center" style="width: <?php echo e($optOutW); ?>%">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($optOutW > 10): ?> <span class="text-[10px] font-bold text-white"><?php echo e(round($optOutW)); ?>%</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-2">
                    <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-green-500"></div><span class="text-xs text-gray-500">Opted In (<?php echo e($this->patientStats['consented']); ?>)</span></div>
                    <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-gray-300"></div><span class="text-xs text-gray-500">Pending</span></div>
                    <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-red-400"></div><span class="text-xs text-gray-500">Opted Out (<?php echo e($this->patientStats['opted_out']); ?>)</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pharmacy Performance Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Pharmacy Performance Ranking</h3>
                <p class="text-sm text-gray-500 mt-1">Ranked by active patient count</p>
            </div>
            <div class="flex items-center gap-1 px-3 py-1.5 bg-green-50 rounded-lg">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <span class="text-xs font-medium text-green-700"><?php echo e($this->pharmacyRanking->count()); ?> pharmacies</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="text-left p-4 font-semibold text-gray-600 w-12">#</th>
                        <th class="text-left p-4 font-semibold text-gray-600">Pharmacy</th>
                        <th class="text-center p-4 font-semibold text-gray-600">Active Patients</th>
                        <th class="text-center p-4 font-semibold text-gray-600">Active Journeys</th>
                        <th class="text-center p-4 font-semibold text-gray-600">Orders Completed</th>
                        <th class="text-center p-4 font-semibold text-gray-600">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->pharmacyRanking; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $pharmacy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-green-50/30 transition">
                            <td class="p-4">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($index === 0): ?>
                                    <span class="inline-flex items-center justify-center w-7 h-7 bg-yellow-100 rounded-full text-yellow-700 text-xs font-bold">1</span>
                                <?php elseif($index === 1): ?>
                                    <span class="inline-flex items-center justify-center w-7 h-7 bg-gray-100 rounded-full text-gray-600 text-xs font-bold">2</span>
                                <?php elseif($index === 2): ?>
                                    <span class="inline-flex items-center justify-center w-7 h-7 bg-amber-50 rounded-full text-amber-700 text-xs font-bold">3</span>
                                <?php else: ?>
                                    <span class="text-gray-400 font-mono pl-2"><?php echo e($index + 1); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="p-4">
                                <p class="font-semibold text-gray-900"><?php echo e($pharmacy->name); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($pharmacy->city ?? ''); ?></p>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-bold"><?php echo e($pharmacy->active_patients); ?></span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="text-sm font-medium text-gray-700"><?php echo e($pharmacy->active_journeys); ?></span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="text-sm font-bold text-green-600"><?php echo e($pharmacy->completed_orders); ?></span>
                            </td>
                            <td class="p-4 text-center">
                                <?php $perfScore = $pharmacy->active_patients > 0 ? min(100, round(($pharmacy->completed_orders / max(1, $pharmacy->active_patients)) * 100)) : 0; ?>
                                <div class="flex items-center gap-2 justify-center">
                                    <div class="w-16 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full <?php echo e($perfScore >= 70 ? 'bg-green-500' : ($perfScore >= 40 ? 'bg-amber-500' : 'bg-red-400')); ?>" style="width: <?php echo e($perfScore); ?>%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500"><?php echo e($perfScore); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\packages\spar-core\src/../resources/views/livewire/admin/spar-reporting.blade.php ENDPATH**/ ?>