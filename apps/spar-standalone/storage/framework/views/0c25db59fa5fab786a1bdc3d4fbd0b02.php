<div class="max-w-md mx-auto">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 'otp'): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1">Verify it's you</h2>
            <p class="text-sm text-gray-500 mb-4">
                We've sent a 6-digit code to your registered contact. Enter it to view your medication.
            </p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('dev_otp')): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-3 text-xs text-blue-700">
                    Dev code: <strong><?php echo e(session('dev_otp')); ?></strong>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['otp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600 mb-2"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($error): ?> <p class="text-sm text-red-600 mb-2"><?php echo e($error); ?></p> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <input type="text" wire:model="otp" inputmode="numeric" maxlength="6"
                   class="w-full text-center text-2xl tracking-[0.5em] font-mono border border-gray-300 rounded-xl py-3 mb-4"
                   placeholder="______" />

            <button wire:click="verifyOtp"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3">
                Verify
            </button>
        </div>

    
    <?php elseif($step === 'consent'): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1">Your consent</h2>
            <p class="text-sm text-gray-500 mb-4">
                Before we show your medication details, we need your permission to process your
                health information and send you medication reminders.
            </p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('declined')): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
                    You've opted out. You can consent again below at any time to use the service.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="bg-gray-50 rounded-xl p-4 text-xs text-gray-600 mb-4 max-h-40 overflow-y-auto">
                <p class="mb-2 font-medium text-gray-700">What you're agreeing to:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><?php echo e(config('spar.branding.name', 'SPAR Pharmacy')); ?> may process your prescription
                        and contact details to manage your chronic medication.</li>
                    <li>We may send you medication collection and renewal reminders.</li>
                    <li>You can withdraw consent at any time, which stops all reminders.</li>
                </ul>
                <p class="mt-2 text-gray-400">Consent version <?php echo e(config('spar.consent_version', '1.0')); ?></p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($error): ?> <p class="text-sm text-red-600 mb-2"><?php echo e($error); ?></p> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <label class="flex items-start gap-2 mb-4 cursor-pointer">
                <input type="checkbox" wire:model="consentAccepted" class="mt-1 rounded border-gray-300 text-green-600" />
                <span class="text-sm text-gray-700">
                    I have read and I give my consent as described above.
                </span>
            </label>

            <button wire:click="grantConsent"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                I consent &mdash; continue
            </button>
            <button wire:click="declineConsent"
                    class="w-full text-gray-500 text-sm py-2">
                No thanks
            </button>
        </div>

    
    <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-3 my-4">
                <p class="text-sm text-green-800"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php $patient = $this->sparPatient; ?>

        <div class="my-6">
            <h2 class="text-xl font-bold text-gray-900">Hi, <?php echo e($patient?->first_name ?: $patient?->display_name); ?>!</h2>
            <p class="text-sm text-gray-500">Here's your medication status.</p>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('spar.online_consult.enabled') && config('spar.online_consult.url')): ?>
            <a href="<?php echo e(config('spar.online_consult.url')); ?>" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?php echo e(config('spar.online_consult.label', 'Get a new prescription online')); ?>

            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->renewalDue): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Prescription renewal needed</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Your prescription has reached its final repeat. To continue your medication
                    you'll need a new script.
                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('spar.host_mode', 'integrated') === 'integrated'): ?>
                    <a href="<?php echo e(config('app.url')); ?>" target="_blank"
                       class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                        Book an online consultation
                    </a>
                    <p class="text-xs text-gray-400 text-center">or renew with your own doctor</p>
                <?php else: ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('spar.online_consult.enabled') && config('spar.online_consult.url')): ?>
                        <a href="<?php echo e(config('spar.online_consult.url')); ?>" target="_blank" rel="noopener"
                           class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                            <?php echo e(config('spar.online_consult.label', 'Get a new prescription online')); ?>

                        </a>
                        <p class="text-xs text-gray-400 text-center">or arrange a new script with your own doctor, then visit
                            <?php echo e($patient?->pharmacy?->name ?? 'your SPAR pharmacy'); ?>.</p>
                    <?php else: ?>
                        <div class="bg-gray-50 rounded-xl p-3 text-sm text-gray-700">
                            Please arrange a new prescription with your doctor, then visit
                            <?php echo e($patient?->pharmacy?->name ?? 'your SPAR pharmacy'); ?> to continue.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->journeys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">Prescription</h3>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journey->patient && !$journey->patient->is_primary_member): ?>
                            <p class="text-xs text-gray-500">
                                For dependant: <?php echo e($journey->patient->first_name ?: 'Dependant #'.$journey->patient->dependent_code); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        <?php echo e($journey->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'); ?>">
                        <?php echo e($journey->status === 'renewal_due' ? 'Renewal Due' : 'Active'); ?>

                    </span>
                </div>
                <p class="text-sm text-gray-600">
                    Dispenses: <?php echo e($journey->dispenses_completed); ?> / <?php echo e($journey->total_dispenses); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($journey->medications)): ?>
                    <ul class="mt-2 text-sm text-gray-700 list-disc list-inside">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $journey->medications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $med): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($med['name'] ?? ''); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                <p class="text-amber-800">No active prescription found on your profile.</p>
                <p class="text-sm text-amber-600 mt-2">Contact your SPAR pharmacy for assistance.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <a href="<?php echo e(route('my-meds.history')); ?>" class="block text-center text-sm text-green-700 py-3">
            View full history
        </a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\packages\spar-core\src/../resources/views/livewire/my-meds-tracker.blade.php ENDPATH**/ ?>