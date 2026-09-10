<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('spar.branding.name', 'SPAR Meds')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <div class="mx-auto max-w-md">
        <header class="px-4 py-4 text-center text-white" style="background: <?php echo e(config('spar.branding.primary_color', '#006B3F')); ?>;">
            <div class="mx-auto mb-1 inline-block rounded bg-white px-3 py-1">
                <img src="<?php echo e(asset('img/spar-logo.jpg')); ?>" alt="<?php echo e(config('spar.branding.name', 'SPAR Meds')); ?>" class="h-6 w-auto">
            </div>
            <div class="text-xs opacity-90">My Chronic Medication</div>
        </header>

        <main class="px-4 py-4">
            <?php echo e($slot ?? ''); ?>

            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\apps\spar-standalone\resources\views/layouts/patient.blade.php ENDPATH**/ ?>