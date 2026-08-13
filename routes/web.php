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
Route::view('offline', 'offline');
Route::get('assessment/{slug}', \App\Livewire\Patient\Assessment::class)->name('assessment.start');
Route::get('doctors/apply', \App\Livewire\DoctorApply::class)->name('doctors.apply');
Route::get('blog', \App\Livewire\Blog\BlogIndex::class)->name('blog');
Route::get('blog/{slug}', \App\Livewire\Blog\BlogShow::class)->name('blog.show');

// AI Health Assistant (public, rate-limited)
Route::post('api/ai-assistant', [\App\Http\Controllers\AiAssistantController::class, 'ask'])
    ->name('ai.ask');

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
        Route::get('/medications', \App\Livewire\Admin\MedicationCatalog::class)->name('admin.medications');
        Route::get('/ai', \App\Livewire\Admin\AiManagement::class)->name('admin.ai');
        Route::get('/partners', \App\Livewire\Admin\Partners::class)->name('admin.partners');
        Route::get('/analytics', \App\Livewire\Admin\Analytics::class)->name('admin.analytics');
        Route::get('/newsletters', \App\Livewire\Admin\Newsletters::class)->name('admin.newsletters');
        Route::get('/reviews', \App\Livewire\Admin\Reviews::class)->name('admin.reviews');
        Route::get('/pharmacy-orders', \App\Livewire\Admin\PharmacyOrders::class)->name('admin.pharmacy-orders');
        Route::get('/languages', \App\Livewire\Admin\LanguageSettings::class)->name('admin.languages');
        Route::get('/audit-log', \App\Livewire\Admin\AuditLog::class)->name('admin.audit-log');
        Route::get('/doctor-applications', \App\Livewire\Admin\DoctorApplications::class)->name('admin.doctor-applications');
        Route::get('/failed-payments', \App\Livewire\Admin\FailedPayments::class)->name('admin.failed-payments');
        Route::get('/blog', \App\Livewire\Admin\BlogManagement::class)->name('admin.blog');
    });

    // Doctor dashboard (Livewire - real data)
    Route::get('doctor/dashboard', \App\Livewire\Doctor\Dashboard::class)
        ->middleware(['role:doctor', 'doctor.availability'])
        ->name('doctor.dashboard');

    // Doctor my patients
    Route::get('doctor/patients', \App\Livewire\Doctor\MyPatients::class)
        ->middleware('role:doctor')
        ->name('doctor.patients');

    // Doctor prescriptions
    Route::get('doctor/prescriptions', \App\Livewire\Doctor\MyPrescriptions::class)
        ->middleware('role:doctor')
        ->name('doctor.prescriptions');

    // Doctor messages
    Route::get('doctor/messages', \App\Livewire\Doctor\Messages::class)
        ->middleware('role:doctor')
        ->name('doctor.messages');

    // Doctor consultation screen
    Route::get('doctor/consultation/{appointment}', \App\Livewire\Doctor\ConsultationScreen::class)
        ->middleware('role:doctor')
        ->name('doctor.consultation');

    // Doctor prescription builder
    Route::get('doctor/prescription/{consultation}', \App\Livewire\Doctor\PrescriptionBuilder::class)
        ->middleware('role:doctor')
        ->name('doctor.prescription');

    // Doctor availability management
    Route::get('doctor/availability', \App\Livewire\Doctor\ManageAvailability::class)
        ->middleware('role:doctor')
        ->name('doctor.availability');

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

    // Patient prescriptions (view only — no download)
    Route::get('prescriptions', \App\Livewire\Patient\MyPrescriptions::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.prescriptions');

    // Patient leave review after consultation
    Route::get('review/{consultation}', \App\Livewire\Patient\LeaveReview::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.review');

    // Patient progress tracking
    Route::get('progress', \App\Livewire\Patient\ProgressTracker::class)
        ->middleware(['role:patient', 'onboarding'])
        ->name('patient.progress');

    Route::get('subscription/checkout', [SubscriptionController::class, 'checkout'])
        ->middleware(['role:patient'])
        ->name('subscription.checkout');

    Route::get('subscription/success', [SubscriptionController::class, 'success'])
        ->middleware(['role:patient'])
        ->name('subscription.success');

    Route::get('subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware(['role:patient'])
        ->name('subscription.cancel');

    // PDF Downloads (sick notes, medical certificates, and prescriptions)
    Route::get('pdf/sick-note/{consultation}', [PdfController::class, 'sickNote'])->name('pdf.sick-note');
    Route::get('pdf/certificate/{consultation}', [PdfController::class, 'medicalCertificate'])->name('pdf.certificate');
    Route::get('pdf/prescription/{prescription}', [PdfController::class, 'prescription'])->name('pdf.prescription');

    // Payment
    Route::get('payment/checkout/{reference}', [PaymentController::class, 'checkout'])->name('payment.checkout');
    Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

    // Patient search (doctor/admin only)
    Route::get('api/search-patients', function (\Illuminate\Http\Request $request) {
        if (!auth()->user()->isDoctor() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $query = $request->get('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $patients = \App\Models\User::where('role', 'patient')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('id_number', 'like', "%{$query}%")
                  ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", ["%{$query}%"]);
            })
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'initials' => $p->initials,
            ]);

        return response()->json($patients);
    })->name('api.search-patients');
});

// PayFast ITN webhook (no auth — PayFast calls this directly)
Route::post('payment/notify', [PaymentController::class, 'notify'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('payment.notify');

// PayFast Subscription ITN webhook
Route::post('subscription/notify', [SubscriptionController::class, 'notify'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('subscription.notify');

// Pharmacy status update webhook (called by partner pharmacies)
Route::post('api/pharmacy/status', [\App\Http\Controllers\PharmacyWebhookController::class, 'statusUpdate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('pharmacy.webhook');

// WordPress plugin update API (called by partner WP sites)
Route::get('api/wp-plugin/info', [\App\Http\Controllers\WpPluginController::class, 'info'])->name('wp-plugin.info');
Route::get('downloads/zapmed-booking-widget.zip', [\App\Http\Controllers\WpPluginController::class, 'download'])->name('wp-plugin.download');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Public pages
Route::view('faq', 'faq')->name('faq');
Route::view('privacy-policy', 'legal.privacy-policy')->name('privacy-policy');
Route::view('cookie-policy', 'legal.cookie-policy')->name('cookie-policy');
Route::view('terms', 'legal.terms')->name('terms');

// Public doctor profiles
Route::get('doctors/{doctor}', \App\Livewire\Doctor\PublicProfile::class)->name('doctor.profile');

// Newsletter unsubscribe (public, no auth)
Route::get('newsletter/unsubscribe/{token}', function (string $token) {
    $subscriber = \App\Models\NewsletterSubscriber::where('unsubscribe_token', $token)->first();
    if ($subscriber) {
        $subscriber->unsubscribe();
        return view('newsletter.unsubscribed');
    }
    abort(404);
})->name('newsletter.unsubscribe');

// Partner dashboard
Route::get('partner/dashboard', \App\Livewire\Partner\Dashboard::class)
    ->middleware(['auth'])
    ->name('partner.dashboard');

require __DIR__.'/auth.php';


// Treatment pages (catch-all, must be LAST — matches zapmed.co.za URL structure)
Route::get('{slug}', [TreatmentController::class, 'show'])
    ->name('treatments.show')
    ->where('slug', '[a-z0-9\-]+');
