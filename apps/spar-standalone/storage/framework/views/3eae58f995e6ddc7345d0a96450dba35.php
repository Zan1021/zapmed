<div wire:poll.30s>
     <?php $__env->slot('header', null, []); ?> Statistics <?php $__env->endSlot(); ?>

    <?php
        $scopeLabel = match($stats['role']) {
            'super_admin' => 'Platform-wide',
            'group_admin' => 'Your group',
            default => 'Your pharmacy',
        };
    ?>

    <div class="mb-6">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            <?php echo e($scopeLabel); ?> view
        </span>
    </div>

    <!-- Counts -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['role'] === 'super_admin'): ?>
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-xs text-gray-500">Groups</p>
                <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['counts']['groups']); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Pharmacies</p>
            <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['counts']['pharmacies']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Patients</p>
            <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['counts']['patients']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Active patients</p>
            <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['counts']['active_patients']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Active journeys</p>
            <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['counts']['active_journeys']); ?></p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Onboarding funnel -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Onboarding funnel</h3>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['awaiting_contact' => 'Awaiting contact', 'pending_consent' => 'Pending consent', 'active' => 'Active', 'opted_out' => 'Opted out']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="text-gray-600"><?php echo e($label); ?></span>
                    <span class="font-medium"><?php echo e($stats['onboarding_funnel'][$key]); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
                <span class="text-gray-600">Activation rate</span>
                <span class="font-semibold text-green-700"><?php echo e($stats['onboarding_funnel']['activation_rate']); ?>%</span>
            </div>
        </div>

        <!-- Consent -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Consent (POPIA)</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-green-700">Opted in</span><span class="font-medium"><?php echo e($stats['consent']['opted_in']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-amber-600">Pending</span><span class="font-medium"><?php echo e($stats['consent']['pending']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Opted out</span><span class="font-medium"><?php echo e($stats['consent']['opted_out']); ?></span></div>
            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
                <span class="text-gray-600">Consent rate</span>
                <span class="font-semibold text-green-700"><?php echo e($stats['consent']['consent_rate']); ?>%</span>
            </div>
        </div>

        <!-- Adherence -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Adherence</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Collected</span><span class="font-medium"><?php echo e($stats['adherence']['collected']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Upcoming</span><span class="font-medium"><?php echo e($stats['adherence']['upcoming']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Overdue</span><span class="font-medium"><?php echo e($stats['adherence']['overdue']); ?></span></div>
        </div>

        <!-- Renewals + Orders -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Renewals &amp; orders</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Renewals due</span><span class="font-medium"><?php echo e($stats['renewals']['due']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Renewed</span><span class="font-medium"><?php echo e($stats['renewals']['renewed']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Lapsed (30d+)</span><span class="font-medium"><?php echo e($stats['renewals']['lapsed']); ?></span></div>
            <div class="mt-2 pt-2 border-t border-gray-100"></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Orders ready</span><span class="font-medium"><?php echo e($stats['orders']['ready']); ?></span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Reminders sent</span><span class="font-medium"><?php echo e($stats['messaging']['reminders_sent']); ?></span></div>
        </div>
    </div>

    <!-- Leaderboard (super/group only) -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($stats['leaderboard'])): ?>
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Pharmacy leaderboard — consent rate</h3>
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr><th class="py-1">Pharmacy</th><th class="py-1 text-center">Patients</th><th class="py-1 text-right">Consent rate</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $stats['leaderboard']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="py-2"><?php echo e($row['pharmacy']); ?></td>
                            <td class="py-2 text-center"><?php echo e($row['patients']); ?></td>
                            <td class="py-2 text-right font-medium"><?php echo e($row['consent_rate']); ?>%</td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\packages\spar-core\src/../resources/views/livewire/admin/spar-stats.blade.php ENDPATH**/ ?>