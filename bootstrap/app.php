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
            'doctor.availability' => \App\Http\Middleware\EnsureDoctorHasAvailability::class,
        ]);

        // Security headers on all responses
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);

        // Set locale from user preference
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);

        // Track partner referrals on all web requests
        $middleware->appendToGroup('web', \App\Http\Middleware\TrackReferral::class);

        // Session security (inactivity timeout)
        $middleware->appendToGroup('web', \App\Http\Middleware\SessionSecurity::class);

        // Medical data access logging
        $middleware->appendToGroup('web', \App\Http\Middleware\LogMedicalAccess::class);

        // Apply 2FA check to all authenticated web routes
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureTwoFactorVerified::class);

        $middleware->validateCsrfTokens(except: [
            'payment/notify', // PayFast ITN webhook
            'subscription/notify', // PayFast subscription webhook
            'api/pharmacy/status', // Pharmacy status webhook
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
