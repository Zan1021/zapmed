<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('spar.branding.name', 'SPAR Meds')); ?> — Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white" style="border-color: <?php echo e(config('spar.branding.primary_color', '#006B3F')); ?>22;">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="<?php echo e(route('spar.dashboard')); ?>" class="flex items-center gap-2">
                <img src="<?php echo e(asset('img/spar-logo.jpg')); ?>" alt="<?php echo e(config('spar.branding.name', 'SPAR Meds')); ?>" class="h-8 w-auto">
                <span class="text-lg font-semibold" style="color: <?php echo e(config('spar.branding.primary_color', '#006B3F')); ?>;">Meds</span>
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="<?php echo e(route('spar.dashboard')); ?>" class="hover:underline">Dashboard</a>
                <a href="<?php echo e(route('spar.patients')); ?>" class="hover:underline">Patients</a>
                <a href="<?php echo e(route('spar.capture')); ?>" class="hover:underline">Capture</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(route('admin.spar.stats')); ?>" class="hover:underline">Stats</a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class)->isSuperAdmin()): ?>
                        <a href="<?php echo e(route('admin.spar.groups')); ?>" class="hover:underline">Groups</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->canManageUsers()): ?>
                        <a href="<?php echo e(route('admin.staff')); ?>" class="hover:underline">Staff</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="text-red-600 hover:underline">Log out</button>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        <?php echo e($slot ?? ''); ?>

        <?php echo $__env->yieldContent('content'); ?>
    </main>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\apps\spar-standalone\resources\views/layouts/staff.blade.php ENDPATH**/ ?>