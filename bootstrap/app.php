<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
        ]);

        // Apply 2FA check to all authenticated web routes
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureTwoFactorVerified::class);

        $middleware->validateCsrfTokens(except: [
            'payment/notify', // PayFast ITN webhook
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
