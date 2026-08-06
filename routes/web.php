<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TreatmentController;
use App\Livewire\Patient\BookAppointment;
use App\Livewire\Patient\MyAppointments;
use App\Livewire\Patient\Onboarding;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('treatments/{slug}', [TreatmentController::class, 'show'])->name('treatments.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')
        ->middleware('onboarding')
        ->name('dashboard');

    // Doctor dashboard (Livewire - real data)
    Route::get('doctor/dashboard', \App\Livewire\Doctor\Dashboard::class)
        ->middleware('role:doctor')
        ->name('doctor.dashboard');

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

    // Payment
    Route::get('payment/checkout/{reference}', [PaymentController::class, 'checkout'])->name('payment.checkout');
    Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
});

// PayFast ITN webhook (no auth — PayFast calls this directly)
Route::post('payment/notify', [PaymentController::class, 'notify'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('payment.notify');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
