<div>
     <?php $__env->slot('header', null, []); ?> SPAR Exceptions <?php $__env->endSlot(); ?>

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6">
        <button wire:click="$set('filter', 'all')" class="px-4 py-2 text-sm rounded-lg transition <?php echo e($filter === 'all' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'); ?>">All</button>
        <button wire:click="$set('filter', 'overdue')" class="px-4 py-2 text-sm rounded-lg transition <?php echo e($filter === 'overdue' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'); ?>">Overdue Dispenses</button>
        <button wire:click="$set('filter', 'renewals')" class="px-4 py-2 text-sm rounded-lg transition <?php echo e($filter === 'renewals' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'); ?>">Missing Renewals</button>
        <button wire:click="$set('filter', 'unresponsive')" class="px-4 py-2 text-sm rounded-lg transition <?php echo e($filter === 'unresponsive' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'); ?>">Unresponsive</button>
    </div>

    <!-- Overdue Dispenses -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filter === 'all' || $filter === 'overdue'): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Overdue Dispenses</h3>
                <span class="text-sm text-red-600"><?php echo e($this->overdueDispenses->count()); ?> patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Days Overdue</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->overdueDispenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dispense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900"><?php echo e($dispense->patient->display_name); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->journey->pharmacy->name ?? '-'); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->due_date->format('d M Y')); ?></td>
                                <td class="p-3">
                                    <span class="text-red-600 font-medium"><?php echo e($dispense->due_date->diffInDays(now())); ?> days</span>
                                </td>
                                <td class="p-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dispense->reminded_at): ?>
                                        <span class="text-green-600"><?php echo e($dispense->reminded_at->format('d M')); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not yet</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No overdue dispenses.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Missing Renewals -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filter === 'all' || $filter === 'renewals'): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Missing Renewals (7+ days overdue)</h3>
                <span class="text-sm text-amber-600"><?php echo e($this->missingRenewals->count()); ?> journeys</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Renewal Due</th>
                            <th class="text-left p-3 font-medium text-gray-600">Doctor</th>
                            <th class="text-left p-3 font-medium text-gray-600">Dispenses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->missingRenewals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900"><?php echo e($journey->patient->display_name); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($journey->pharmacy->name ?? '-'); ?></td>
                                <td class="p-3 text-amber-600"><?php echo e($journey->renewal_due_date?->format('d M Y') ?? '-'); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($journey->doctor_name ?? '-'); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($journey->dispenses_completed); ?>/<?php echo e($journey->total_dispenses); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No missing renewals.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Unresponsive Patients -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filter === 'all' || $filter === 'unresponsive'): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Unresponsive (10+ days after reminder)</h3>
                <span class="text-sm text-gray-500"><?php echo e($this->unresponsivePatients->count()); ?> patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminder Sent</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->unresponsivePatients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dispense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900"><?php echo e($dispense->patient->display_name); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->journey->pharmacy->name ?? '-'); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->reminded_at?->format('d M Y') ?? '-'); ?></td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->due_date->format('d M Y')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="p-6 text-center text-gray-500">No unresponsive patients.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\packages\spar-core\src/../resources/views/livewire/admin/spar-exceptions.blade.php ENDPATH**/ ?>