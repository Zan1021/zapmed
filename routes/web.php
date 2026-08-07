<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TreatmentController;
use App\Livewire\Patient\BookAppointment;
use App\Livewire\Patient\MyAppointments;
use App\Livewire\Patient\Onboarding;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('treatments/{slug}', [TreatmentController::class, 'show'])->name('treatments.show');
Route::get('assessment/{slug}', \App\Livewire\Patient\Assessment::class)->name('assessment.start');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')
        ->middleware('onboarding')
        ->name('dashboard');

    // Admin panel
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin.dashboard');
        Route::get('/users', \App\Livewire\Admin\UserManagement::class)->name('admin.users');
        Route::get('/appointments', \App\Livewire\Admin\Appointments::class)->name('admin.appointments');
        Route::get('/payments', \App\Livewire\Admin\Payments::class)->name('admin.payments');
        Route::get('/subscriptions', \App\Livewire\Admin\SubscriptionPlans::class)->name('admin.subscriptions');
        Route::get('/audit-log', \App\Livewire\Admin\AuditLog::class)->name('admin.audit-log');
    });

    // Doctor dashboard (Livewire - real data)
    Route::get('doctor/dashboard', \App\Livewire\Doctor\Dashboard::class)
        ->middleware('role:doctor')
        ->name('doctor.dashboard');

    // Doctor consultation screen
    Route::get('doctor/consultation/{appointment}', \App\Livewire\Doctor\ConsultationScreen::class)
        ->middleware('role:doctor')
        ->name('doctor.consultation');

    // Doctor prescription builder
    Route::get('doctor/prescription/{consultation}', \App\Livewire\Doctor\PrescriptionBuilder::class)
        ->middleware('role:doctor')
        ->name('doctor.prescription');

    // Patient onboarding
    Route::get('onboarding', Onboarding::class)
        ->middleware('role:patient')
        ->name('patient.onboarding');

    // Patient booking
    Route::get('book', BookAppointment::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.book');

    Route::get('appointments', MyAppointments::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.appointments');

    // Patient video call
    Route::get('video/{appointment}', \App\Livewire\Patient\VideoCall::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.video');

    // Patient subscription
    Route::get('subscription', \App\Livewire\Patient\MySubscription::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.subscription');

    Route::get('subscription/checkout', [SubscriptionController::class, 'checkout'])
        ->middleware(['role:patient'])
        ->name('subscription.checkout');

    Route::get('subscription/success', [SubscriptionController::class, 'success'])
        ->middleware(['role:patient'])
        ->name('subscription.success');

    Route::get('subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware(['role:patient'])
        ->name('subscription.cancel');

    // PDF Downloads
    Route::get('pdf/prescription/{prescription}', [PdfController::class, 'prescription'])->name('pdf.prescription');
    Route::get('pdf/sick-note/{consultation}', [PdfController::class, 'sickNote'])->name('pdf.sick-note');
    Route::get('pdf/certificate/{consultation}', [PdfController::class, 'medicalCertificate'])->name('pdf.certificate');

    // Payment
    Route::get('payment/checkout/{reference}', [PaymentController::class, 'checkout'])->name('payment.checkout');
    Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
});

// PayFast ITN webhook (no auth — PayFast calls this directly)
Route::post('payment/notify', [PaymentController::class, 'notify'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('payment.notify');

// PayFast Subscription ITN webhook
Route::post('subscription/notify', [SubscriptionController::class, 'notify'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('subscription.notify');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
