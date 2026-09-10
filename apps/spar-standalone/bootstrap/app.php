<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPAR middleware aliases resolve to the spar-core package classes.
        // The standalone host binds its own identity provider, so spar.scope /
        // spar.timeout read the pharmacy scope from that provider (no role enum).
        $middleware->alias([
            'spar.scope' => \Zapmed\SparCore\Http\Middleware\EnsureSparPharmacyScope::class,
            'spar.timeout' => \Zapmed\SparCore\Http\Middleware\SparSessionTimeout::class,
            'spar.patient.session' => \Zapmed\SparCore\Http\Middleware\EnsureSparPatientSession::class,
            'spar.headers' => \Zapmed\SparCore\Http\Middleware\SparSecurityHeaders::class,
        ]);

        // Baseline security headers on every web response (Phase 10.3).
        $middleware->web(append: [
            \Zapmed\SparCore\Http\Middleware\SparSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
