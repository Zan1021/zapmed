<div>
     <?php $__env->slot('header', null, []); ?> Pharmacy Dashboard <?php $__env->endSlot(); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$this->pharmacy): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-center">
            <p class="text-amber-800">Your account is not linked to a SPAR pharmacy. Please contact your administrator.</p>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="bg-white rounded-xl p-6 mb-8 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900"><?php echo e($this->pharmacy->name); ?></h2>
                    <p class="text-gray-500 mt-1"><?php echo e($this->pharmacy->city ?? ''); ?> <?php echo e($this->pharmacy->province ?? ''); ?></p>
                </div>
                <div class="text-right hidden sm:block">
                    <p class="text-sm text-gray-500"><?php echo e(now()->format('l, j M Y')); ?></p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Active Patients</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo e($this->stats['active_patients'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Pending Orders</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?php echo e($this->stats['pending_orders'] ?? 0); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($this->stats['ready_orders'] ?? 0) > 0): ?>
                    <p class="text-xs text-green-600 mt-1"><?php echo e($this->stats['ready_orders']); ?> ready for collection</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Renewals Due</p>
                <p class="text-2xl font-bold text-purple-600 mt-1"><?php echo e($this->stats['renewals_due'] ?? 0); ?></p>
                <p class="text-xs text-gray-500 mt-1"><?php echo e($this->stats['upcoming_dispenses'] ?? 0); ?> dispenses this week</p>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Pending Orders -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Orders</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->pendingOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo e($order->patient->display_name); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo e($order->reference); ?> &middot; <?php echo e(ucfirst($order->type)); ?></p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    <?php echo e($order->status === 'preparing' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'); ?>">
                                    <?php echo e(ucfirst($order->status)); ?>

                                </span>
                            </div>
                            <div class="flex gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->status === 'requested'): ?>
                                    <button wire:click="startPreparing(<?php echo e($order->id); ?>)" class="px-3 py-1 text-xs bg-amber-50 text-amber-700 rounded hover:bg-amber-100 transition">Start Preparing</button>
                                <?php elseif($order->status === 'preparing'): ?>
                                    <button wire:click="markReady(<?php echo e($order->id); ?>)" class="px-3 py-1 text-xs bg-green-50 text-green-700 rounded hover:bg-green-100 transition">Mark Ready</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="p-8 text-center text-gray-500 text-sm">No pending orders.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Ready for Collection -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Ready for Collection/Delivery</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->readyOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?php echo e($order->patient->display_name); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($order->reference); ?> &middot; Ready <?php echo e($order->ready_at?->diffForHumans()); ?></p>
                            </div>
                            <button wire:click="markCompleted(<?php echo e($order->id); ?>)" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <?php echo e($order->isCollection() ? 'Collected' : 'Delivered'); ?>

                            </button>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="p-8 text-center text-gray-500 text-sm">No orders ready.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Dispenses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Upcoming Dispenses (Next 14 Days)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Dispense #</th>
                            <th class="text-left p-3 font-medium text-gray-600">Medications</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingDispenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dispense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900"><?php echo e($dispense->patient->display_name); ?></td>
                                <td class="p-3">
                                    <span class="<?php echo e($dispense->due_date->isPast() ? 'text-red-600 font-medium' : 'text-gray-600'); ?>">
                                        <?php echo e($dispense->due_date->format('d M Y')); ?>

                                    </span>
                                </td>
                                <td class="p-3 text-gray-600"><?php echo e($dispense->dispense_number); ?>/<?php echo e($dispense->journey->total_dispenses); ?></td>
                                <td class="p-3 text-gray-500 text-xs">
                                    <?php echo e(collect($dispense->journey->medications ?? [])->pluck('name')->take(3)->implode(', ')); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="p-6 text-center text-gray-500">No upcoming dispenses.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\packages\spar-core\src/../resources/views/livewire/pharmacy-dashboard.blade.php ENDPATH**/ ?>