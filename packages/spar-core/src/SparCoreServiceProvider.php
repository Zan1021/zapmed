<?php

namespace Zapmed\SparCore;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Zapmed\SparCore\Livewire\MyMedsLogin;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Livewire\MyMedsHistory;
use Zapmed\SparCore\Livewire\PatientList;
use Zapmed\SparCore\Livewire\PatientDetail;
use Zapmed\SparCore\Livewire\PharmacistCapture;
use Zapmed\SparCore\Livewire\PharmacyDashboard;
use Zapmed\SparCore\Livewire\Admin\SparBanners;
use Zapmed\SparCore\Livewire\Admin\SparConsents;
use Zapmed\SparCore\Livewire\Admin\SparDashboard;
use Zapmed\SparCore\Livewire\Admin\SparExceptions;
use Zapmed\SparCore\Livewire\Admin\SparGroups;
use Zapmed\SparCore\Livewire\Admin\SparImports;
use Zapmed\SparCore\Livewire\Admin\SparPharmacies;
use Zapmed\SparCore\Livewire\Admin\SparReporting;
use Zapmed\SparCore\Livewire\Admin\SparStats;

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

        // Explicitly register the package's Livewire components under stable
        // aliases. Routing to them by class only registers them for the render
        // request; the subsequent /livewire/update POST must be able to resolve
        // the component name back to its class. Without this, ComponentRegistry
        // throws ComponentNotFoundException on update, which Livewire surfaces
        // as LivewireReleaseTokenMismatchException → a spurious 419
        // "This page has expired" on every wire:click. (App\Livewire components
        // are auto-discovered, which is why the login screen was unaffected.)
        $this->registerLivewireComponents();
    }

    private function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $components = [
            'spar.my-meds-login'      => MyMedsLogin::class,
            'spar.my-meds-tracker'    => MyMedsTracker::class,
            'spar.my-meds-history'    => MyMedsHistory::class,
            'spar.patient-list'       => PatientList::class,
            'spar.patient-detail'     => PatientDetail::class,
            'spar.pharmacist-capture' => PharmacistCapture::class,
            'spar.pharmacy-dashboard' => PharmacyDashboard::class,
            'spar.admin.banners'      => SparBanners::class,
            'spar.admin.consents'     => SparConsents::class,
            'spar.admin.dashboard'    => SparDashboard::class,
            'spar.admin.exceptions'   => SparExceptions::class,
            'spar.admin.groups'       => SparGroups::class,
            'spar.admin.imports'      => SparImports::class,
            'spar.admin.pharmacies'   => SparPharmacies::class,
            'spar.admin.reporting'    => SparReporting::class,
            'spar.admin.stats'        => SparStats::class,
        ];

        foreach ($components as $alias => $class) {
            Livewire::component($alias, $class);
        }
    }
}
