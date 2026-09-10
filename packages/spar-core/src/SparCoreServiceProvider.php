<?php

namespace Zapmed\SparCore;

use Illuminate\Support\ServiceProvider;

/**
 * SPAR core package provider (spec FR-1.1). Registers the SPAR domain's config,
 * migrations, and views so both hosts (ZapMed integrated + standalone) load one
 * shared source of truth. Contract bindings are host responsibility (the host's
 * own provider binds SparIdentityProvider / TelehealthBridge / MessagingChannel
 * / AuditLogger to integrated or standalone implementations).
 *
 * NOTE: during Phase 3 the domain code is being migrated in incrementally. This
 * provider registers only what has actually been moved, so the app stays
 * bootable at every step.
 */
class SparCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config if present (config/spar.php moves in during 3.3).
        $config = __DIR__ . '/../config/spar.php';
        if (file_exists($config)) {
            $this->mergeConfigFrom($config, 'spar');
        }
    }

    public function boot(): void
    {
        // Migrations (moved in during 3.4).
        $migrations = __DIR__ . '/../database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        // Views (moved in during 3.3), namespaced 'spar::'.
        $views = __DIR__ . '/../resources/views';
        if (is_dir($views)) {
            $this->loadViewsFrom($views, 'spar');
        }

        // Routes (moved in during 3.3).
        $routes = __DIR__ . '/../routes/spar.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
