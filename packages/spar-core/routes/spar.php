<?php

use Illuminate\Support\Facades\Route;
use Zapmed\SparCore\Livewire\MyMedsLogin;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Livewire\MyMedsHistory;
use Zapmed\SparCore\Livewire\PharmacyDashboard;
use Zapmed\SparCore\Livewire\PatientList;
use Zapmed\SparCore\Livewire\PharmacistCapture;
use Zapmed\SparCore\Livewire\Admin\SparDashboard;
use Zapmed\SparCore\Livewire\Admin\SparGroups;
use Zapmed\SparCore\Livewire\Admin\SparStats;
use Zapmed\SparCore\Livewire\Admin\SparPharmacies;
use Zapmed\SparCore\Livewire\Admin\SparImports;
use Zapmed\SparCore\Livewire\Admin\SparBanners;
use Zapmed\SparCore\Livewire\Admin\SparExceptions;
use Zapmed\SparCore\Livewire\Admin\SparConsents;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;

/*
|--------------------------------------------------------------------------
| SPAR Core Routes
|--------------------------------------------------------------------------
|
| Shipped by the spar-core package and loaded by SparCoreServiceProvider.
| Middleware groups are HOST-CONFIGURED via config('spar.route_middleware.*')
| so each host applies its own auth/role gating:
|   - staff : authenticated pharmacy-staff area (ZapMed = auth,verified,
|             role:pharmacy_staff,spar.scope,spar.timeout)
|   - admin : authenticated admin area           (ZapMed = auth,verified,role:admin)
| The patient tracker routes are public (signed-link + no-login session).
|
| Route NAMES are stable across hosts so all url()/route() refs keep working.
|
*/

$staffMiddleware = config('spar.route_middleware.staff', ['web', 'auth']);
$adminMiddleware = config('spar.route_middleware.admin', ['web', 'auth']);
$publicMiddleware = config('spar.route_middleware.public', ['web']);

// ---- Public patient surface (no User login, spec FR-9) --------------------

Route::middleware($publicMiddleware)->group(function () {
    // Phone-entry OTP login path. Throttled — public + issues OTPs (Phase 10.3).
    Route::get('my-meds/login', MyMedsLogin::class)->middleware('throttle:30,1')->name('my-meds.login');

    // Signed-link entry — establishes a scoped SPAR patient session (NOT a
    // User login), then the component handles OTP re-verify -> consent -> dash.
    Route::get('track/{patient}', function (SparPatient $patient) {
        abort_unless(request()->hasValidSignature(), 403);

        app(SparPatientSession::class)->establish($patient);

        return redirect()->route('my-meds.track');
    })->middleware(['signed', 'throttle:30,1'])->name('spar.track');

    // The tracker itself (reads the session established above).
    Route::get('my-meds', MyMedsTracker::class)->name('my-meds.track');

    Route::get('my-meds/history', MyMedsHistory::class)
        ->middleware('spar.patient.session')
        ->name('my-meds.history');

    // Banner click tracking — increments clicks then redirects to the target.
    Route::get('b/{banner}', function (\Zapmed\SparCore\Models\SparBanner $banner) {
        $banner->increment('clicks');

        return $banner->link_url
            ? redirect()->away($banner->link_url)
            : redirect()->route('my-meds.track');
    })->name('spar.banner.click');

    // Renewal -> ZapMed teleconsult handoff (signed, integrated only, spec FR-13).
    // Standalone binds NullTelehealthBridge so no handoff link is ever issued.
    Route::get('spar/renewal-handoff/{journey}', function (SparPrescriptionJourney $journey) {
        abort_unless(request()->hasValidSignature(), 403);

        session(['spar_renewal_journey_id' => $journey->id]);

        return redirect()->route(config('spar.renewal_handoff_route', 'dashboard'));
    })->middleware('signed')->name('spar.renewal-handoff');
});

// ---- Pharmacy staff area --------------------------------------------------

Route::middleware($staffMiddleware)->prefix('spar')->group(function () {
    Route::get('/dashboard', PharmacyDashboard::class)->name('spar.dashboard');
    Route::get('/patients', PatientList::class)->name('spar.patients');
    Route::get('/patients/{patient}', \Zapmed\SparCore\Livewire\PatientDetail::class)->name('spar.patients.show');
    Route::get('/capture', PharmacistCapture::class)->name('spar.capture');
});

// ---- Admin area -----------------------------------------------------------

Route::middleware($adminMiddleware)->prefix('admin/spar')->group(function () {
    Route::get('/', SparDashboard::class)->name('admin.spar.dashboard');
    Route::get('/stats', SparStats::class)->name('admin.spar.stats');
    // Reporting merged into the unified Insights page (Phase 8.3). Keep the
    // route name so existing links/bookmarks resolve; it now renders Insights.
    Route::get('/reporting', SparStats::class)->name('admin.spar.reporting');
    Route::get('/groups', SparGroups::class)->name('admin.spar.groups');
    Route::get('/pharmacies', SparPharmacies::class)->name('admin.spar.pharmacies');
    Route::get('/imports', SparImports::class)->name('admin.spar.imports');
    Route::get('/banners', SparBanners::class)->name('admin.spar.banners');
    Route::get('/exceptions', SparExceptions::class)->name('admin.spar.exceptions');
    Route::get('/consent', SparConsents::class)->name('admin.spar.consent');
});
