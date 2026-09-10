<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('spar.branding.name', 'SPAR Meds')); ?> — Sign in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>
<body class="flex min-h-screen items-center justify-center bg-gray-100 text-gray-900">
    <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
        <div class="mb-6 text-center">
            <img src="<?php echo e(asset('img/spar-logo.jpg')); ?>" alt="<?php echo e(config('spar.branding.name', 'SPAR Meds')); ?>" class="mx-auto mb-3 h-12 w-auto">
            <div class="text-sm text-gray-500">Pharmacy staff sign in</div>
        </div>
        <?php echo e($slot ?? ''); ?>

        <?php echo $__env->yieldContent('content'); ?>
    </div>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH C:\Users\zande\Documents\Zapmed\zapmed\apps\spar-standalone\resources\views/layouts/auth.blade.php ENDPATH**/ ?>