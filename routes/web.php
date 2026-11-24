<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SubscriptionController;
use App\Livewire\Patient\BookAppointment;
use App\Livewire\Patient\MyAppointments;
use App\Livewire\Patient\Onboarding;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::view('offline', 'offline');
Route::get('health', \App\Http\Controllers\HealthCheckController::class)->name('health');

// Sentry test route (only available in non-production)
if (app()->environment('local', 'staging')) {
    Route::get('debug-sentry', function () {
        throw new \RuntimeException('Sentry test — if you see this in Sentry, it works!');
    });
}
Route::get('assessment/{slug}', \App\Livewire\Patient\Assessment::class)->name('assessment.start');
// Charge-at-booking onboarding journey (public — no account to START; OTP + account at "details").
// Feature-flagged per slug in config/onboarding.php. Placed before the catch-all {slug} route.
Route::get('start/{slug}', \App\Livewire\Patient\BookingJourney::class)
    ->where('slug', '[a-z0-9\-]+')
    ->name('onboarding.start');
Route::get('doctors/apply', \App\Livewire\DoctorApply::class)->name('doctors.apply');
Route::get('blog', \App\Livewire\Blog\BlogIndex::class)->name('blog');
// Legacy /blog/{slug} path — 301 to the root-level URL (SEO: posts live at root).
Route::get('blog/{slug}', function (string $slug) {
    return redirect("/{$slug}", 301);
})->where('slug', '[a-z0-9\-]+')->name('blog.show');

// Blog category archives — matches legacy WordPress /category/{slug}/ URLs.
Route::get('category/{category}', \App\Livewire\Blog\BlogIndex::class)
    ->where('category', '[a-z0-9\-]+')
    ->name('blog.category');

// XML sitemap (pages, treatments, blog posts, categories).
Route::get('sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// SPAR routes (patient tracker, pharmacy staff, admin) are shipped by the
// spar-core package (packages/spar-core/routes/spar.php) and loaded by
// SparCoreServiceProvider. Middleware groups are host-configured via
// config('spar.route_middleware.*').

// AI Health Assistant (public, rate-limited)
Route::post('api/ai-assistant', [\App\Http\Controllers\AiAssistantController::class, 'ask'])
    ->name('ai.ask');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if (auth()->user()->isDoctor()) {
            return redirect()->route('doctor.dashboard');
        }
        if (auth()->user()->isPharmacyStaff()) {
            return redirect()->route('spar.dashboard');
        }
        return view('dashboard');
    })->middleware('onboarding')->name('dashboard');

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
        Route::get('/stats', \App\Livewire\Admin\Stats::class)->name('admin.stats');
        Route::get('/newsletters', \App\Livewire\Admin\Newsletters::class)->name('admin.newsletters');
        Route::get('/reviews', \App\Livewire\Admin\Reviews::class)->name('admin.reviews');
        Route::get('/pharmacy-orders', \App\Livewire\Admin\PharmacyOrders::class)->name('admin.pharmacy-orders');
        Route::get('/order-board', \App\Livewire\Admin\OrderBoard::class)->name('admin.order-board');
        Route::get('/leads-funnel', \App\Livewire\Admin\LeadsFunnel::class)->name('admin.leads-funnel');
        Route::get('/patient-360', \App\Livewire\Admin\PatientProfile360::class)->name('admin.patient-360');
        Route::get('/alerts', \App\Livewire\Admin\AlertsBoard::class)->name('admin.alerts');
        Route::get('/languages', \App\Livewire\Admin\LanguageSettings::class)->name('admin.languages');
        Route::get('/audit-log', \App\Livewire\Admin\AuditLog::class)->name('admin.audit-log');
        Route::get('/doctor-applications', \App\Livewire\Admin\DoctorApplications::class)->name('admin.doctor-applications');
        Route::get('/failed-payments', \App\Livewire\Admin\FailedPayments::class)->name('admin.failed-payments');
        Route::get('/blog', \App\Livewire\Admin\BlogManagement::class)->name('admin.blog');
        Route::get('/help-center', \App\Livewire\Admin\HelpCenter::class)->name('admin.help-center');
        Route::get('/contro-import', \App\Livewire\Admin\ControImport::class)->name('admin.contro-import');
        Route::get('/crm-analytics', \App\Livewire\Admin\AnalyticsDashboard::class)->name('admin.crm-analytics');
        Route::get('/finance', \App\Livewire\Admin\FinanceReports::class)->name('admin.finance');
        Route::get('/reports', \App\Livewire\Admin\OperationsReportsPage::class)->name('admin.reports');
        Route::get('/operations', \App\Livewire\Admin\OperationsOverview::class)->name('admin.operations');
        Route::get('/repeat-cycles', \App\Livewire\Admin\RepeatCycleSettings::class)->name('admin.repeat-cycles');
        Route::get('/integration-log', \App\Livewire\Admin\IntegrationLogPage::class)->name('admin.integration-log');
        Route::get('/message-templates', \App\Livewire\Admin\MessageTemplateEditor::class)->name('admin.message-templates');
        Route::get('/addons', \App\Livewire\Admin\AddonSettings::class)->name('admin.addons');
        Route::get('/product-list', \App\Livewire\Admin\ProductListImport::class)->name('admin.product-list');
        Route::get('/product-list/export', \App\Http\Controllers\ProductListExportController::class)->name('admin.product-list.export');
        Route::get('/users/{user}/staff', \App\Livewire\Admin\StaffProfileEditor::class)->whereNumber('user')->name('admin.staff-profile');
        Route::get('/reports/{report}/export', \App\Http\Controllers\OperationsReportExportController::class)->where('report', '[a-z_]+')->name('admin.reports.export');
        Route::get('/ai-nudges', \App\Livewire\Admin\AiNudges::class)->name('admin.ai-nudges');
        Route::get('/compliance', \App\Livewire\Admin\ComplianceConsole::class)->name('admin.compliance');
        Route::get('/question-bank', \App\Livewire\Admin\QuestionLibrary::class)->name('admin.question-bank');
        Route::get('/question-bank/categories/{slug?}', \App\Livewire\Admin\CategoryBuilder::class)->name('admin.question-bank.categories');
        Route::get('/question-bank/review', \App\Livewire\Admin\ReviewQueue::class)->name('admin.question-bank.review');

        // SPAR Chronic Medication Module admin routes are shipped by the
        // spar-core package (admin.spar.* names, prefix admin/spar), gated by
        // config('spar.route_middleware.admin').
    });

    // Coach console — accessible to admins AND health coaches (coaches see only their patients;
    // scoping is enforced inside the component). Lives under /admin/coach-console for UI consistency
    // but is gated on role:admin,health_coach rather than the admin-only group above.
    Route::middleware('role:admin,health_coach')->group(function () {
        Route::get('admin/coach-console', \App\Livewire\Admin\CoachConsole::class)->name('admin.coach-console');
        Route::get('admin/coach-recipes', \App\Livewire\Admin\CoachRecipes::class)->name('admin.coach-recipes');
    });

    // Doctor dashboard (Livewire - real data)
    Route::get('doctor/dashboard', \App\Livewire\Doctor\Dashboard::class)
        ->middleware(['role:doctor', 'doctor.availability'])
        ->name('doctor.dashboard');

    // SPAR Pharmacy Staff routes (spar.dashboard/patients/capture) are shipped
    // by the spar-core package, gated by config('spar.route_middleware.staff').

    // SPAR Patient "My Meds" — NO User login (spec FR-9). The patient surface is
    // the tokenised tracker (route 'my-meds.track', public, defined at top) which
    // establishes a scoped SPAR patient session via signed link + OTP re-verify.
    // The old authenticated role:patient My Meds routes are retired.

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

    // Doctor Requests: shared work items between doctors, Operations and coaches (Craig's guide 4.1, 8.1).
    Route::middleware('role:doctor,admin,health_coach')->prefix('doctor/requests')->group(function () {
        Route::get('/', \App\Livewire\Doctor\Requests::class)->name('doctor.requests');
        Route::get('{doctorRequest}', \App\Livewire\Doctor\RequestDetail::class)->whereNumber('doctorRequest')->name('doctor.requests.show');
        Route::get('{doctorRequest}/files/{file}', \App\Http\Controllers\DoctorRequestFileController::class)
            ->whereNumber(['doctorRequest', 'file'])->name('doctor.requests.file');
    });

    // Doctor follow-up requests: the list. Requesting is done from the consultation screen.
    Route::get('doctor/follow-ups', \App\Livewire\Doctor\FollowUps::class)->middleware('role:doctor')->name('doctor.follow-ups');

    // Blood tests (Craig's guide 4.1, 4.2): the doctor's list, and private downloads for the patient and treating doctors only.
    Route::get('doctor/blood-tests', \App\Livewire\Doctor\BloodTests::class)->middleware('role:doctor')->name('doctor.blood-tests');
    Route::middleware('role:doctor,patient')->group(function () {
        Route::get('blood-tests/{bloodTest}/results/{result}', [\App\Http\Controllers\BloodTestFileController::class, 'result'])->whereNumber(['bloodTest', 'result'])->name('bloodtests.result');
        Route::get('blood-tests/{bloodTest}/form', [\App\Http\Controllers\BloodTestFileController::class, 'form'])->whereNumber('bloodTest')->name('bloodtests.form');
    });

    // A patient's uploaded records and results: the patient and treating doctors only (checked in the controller).
    Route::get('health-records/{record}', \App\Http\Controllers\HealthRecordFileController::class)->middleware('role:doctor,patient')->whereNumber('record')->name('health-records.show');

    // A doctor's signature image: Operations and that doctor only (checked in the controller).
    Route::get('staff-signature/{user}', \App\Http\Controllers\StaffSignatureController::class)->middleware('role:doctor,admin')->whereNumber('user')->name('admin.staff-signature');

    // Authorisations: patients nearing their six-month renewal (Craig's guide 4.1).
    Route::get('doctor/authorisations', \App\Livewire\Doctor\Authorisations::class)->middleware('role:doctor')->name('doctor.authorisations');

    // Staff notes addressed to me (doctors, Operations, coaches). Writing a note happens inside a patient screen.
    Route::get('staff-notes', \App\Livewire\Doctor\StaffNotes::class)->middleware('role:doctor,admin,health_coach')->name('staff.notes');

    // Appointment lists and the patient history board (Craig's guide 4.1).
    Route::get('doctor/appointments', \App\Livewire\Doctor\Appointments::class)->middleware('role:doctor')->name('doctor.appointments');
    Route::get('doctor/patient-history', \App\Livewire\Doctor\PatientHistory::class)->middleware('role:doctor')->name('doctor.patient-history');

    // Doctor time tracking (Craig's guide 4.2, 8.1). The export is also open to Operations for the invoice.
    Route::get('doctor/time', \App\Livewire\Doctor\TimeSheet::class)->middleware('role:doctor')->name('doctor.time');
    Route::get('doctor/time/export', \App\Http\Controllers\DoctorTimeExportController::class)->middleware('role:doctor,admin')->name('doctor.time.export');

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

    // My Zapmed tabs (Craig's guide section 3): orders, health, follow-up, notifications, rewards, community.
    Route::middleware(['role:patient', 'onboarding'])->prefix('my')->group(function () {
        Route::get('orders', \App\Livewire\Patient\MyOrders::class)->name('patient.orders');
        Route::get('health', \App\Livewire\Patient\HealthStats::class)->name('patient.health');
        Route::get('follow-up', \App\Livewire\Patient\FollowUp::class)->name('patient.follow-up');
        Route::get('notifications', \App\Livewire\Patient\Notifications::class)->name('patient.notifications');
        Route::get('rewards', \App\Livewire\Patient\Rewards::class)->name('patient.rewards');
        Route::get('community', \App\Livewire\Patient\Community::class)->name('patient.community');
    });

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

    // Coach inbox for staff: the member threads of the coach's assigned members.
    Route::middleware('role:health_coach')->group(function () {
        Route::get('coach-inbox', \App\Livewire\Coach\Inbox::class)->name('coach.inbox');
        Route::get('coach-inbox/{patient}', \App\Livewire\Coach\Thread::class)->name('coach.inbox.thread');
        Route::get('coach-board', \App\Livewire\Coach\Board::class)->name('coach.board');
    });

    // Coach safety flags for staff (doctors, coaches). Not under /coach: those routes are for members with the add-on.
    Route::get('coach-flags', \App\Livewire\Coach\FlagsInbox::class)->middleware('role:doctor,health_coach')->name('coach.flags');
    // One member's tracking summary: the assigned coach and a doctor who has seen the member (checked in the component).
    Route::get('coach-board/{patient}', \App\Livewire\Coach\PatientView::class)->middleware('role:doctor,health_coach')->name('coach.board.patient');

    // Buy the coach add-on (own choice or offered by a doctor). Outside the group below: no add-on needed yet.
    Route::get('coach/join', \App\Livewire\Coach\Join::class)->middleware(['role:patient', 'onboarding'])->name('coach.join');

    // Coach App (weight-loss membership): patient tracking + health coaching. Design: .kiro/specs/dietitian-coach/
    Route::prefix('coach')->middleware(['role:patient', 'onboarding', 'coach.addon'])->group(function () {
        Route::get('/', \App\Livewire\Coach\Today::class)->name('coach.today');
        Route::get('start', \App\Livewire\Coach\Onboarding::class)->name('coach.start');
        Route::get('weight', \App\Livewire\Coach\Weight::class)->name('coach.weight');
        Route::get('weight/log', \App\Livewire\Coach\LogWeight::class)->name('coach.weight.log');
        Route::get('medication', \App\Livewire\Coach\Medication::class)->name('coach.medication');
        Route::get('medication/log', \App\Livewire\Coach\LogDose::class)->name('coach.medication.log');
        Route::get('side-effects', \App\Livewire\Coach\LogSideEffect::class)->name('coach.side-effects');
        Route::get('water', \App\Livewire\Coach\Water::class)->name('coach.water');
        Route::get('reminders', \App\Livewire\Coach\Reminders::class)->name('coach.reminders');
        Route::get('messages', \App\Livewire\Coach\Messages::class)->name('coach.messages');
        Route::get('book', \App\Livewire\Coach\BookCall::class)->name('coach.book');
        Route::get('profile', \App\Livewire\Coach\Profile::class)->name('coach.profile');
        Route::get('milestone', \App\Livewire\Coach\Milestone::class)->name('coach.milestone');
        Route::get('plan', \App\Livewire\Coach\Plan::class)->name('coach.plan');
        Route::get('plan/personalise', \App\Livewire\Coach\Personalise::class)->name('coach.plan.personalise');
        Route::get('recipes', \App\Livewire\Coach\Recipes::class)->name('coach.recipes');
        Route::get('recipes/{recipe}', \App\Livewire\Coach\RecipeShow::class)->name('coach.recipe');
        Route::get('nutrition', \App\Livewire\Coach\Nutrition::class)->name('coach.nutrition');
        Route::get('nutrition/log', \App\Livewire\Coach\LogMeal::class)->name('coach.nutrition.log');
        Route::get('activity', \App\Livewire\Coach\Activity::class)->name('coach.activity');
        Route::get('activity/log', \App\Livewire\Coach\LogActivity::class)->name('coach.activity.log');
        Route::get('cycle', \App\Livewire\Coach\Cycle::class)->name('coach.cycle');
        Route::get('insight', \App\Livewire\Coach\Insight::class)->name('coach.insight');
    });

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

// Public Help Center
Route::get('help', \App\Livewire\HelpCenter\Index::class)->name('help');
Route::get('help/{slug}', \App\Livewire\HelpCenter\Show::class)->name('help.show');
Route::get('faq', fn () => redirect()->route('help'))->name('faq');
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

// New-design treatment category pages (static HTML in resources/treatment-pages-v3,
// assets in public/zapmed-v3). Registered before the catch-all so they win over it.
// Every "Get started" button on these pages goes straight to /start/{slug} (no sign-up first).
foreach (['weight-loss', 'womens-health', 'mens-health', 'sexual-health', 'hair-loss', 'skincare', 'general-health'] as $categoryPage) {
    Route::get($categoryPage, function () use ($categoryPage) {
        return response(file_get_contents(resource_path("treatment-pages-v3/{$categoryPage}.html")), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    })->name("category-page.{$categoryPage}");
}

// Treatment + blog-post + legacy resolver (catch-all, must be LAST —
// matches zapmed.co.za root URL structure: /{treatment} and /{post-slug}).
// Route name kept as 'treatments.show' for backward compatibility with the many
// route('treatments.show', $slug) references in views/controllers — it emits
// /{slug}, which is correct for treatments AND root-level blog posts.
Route::get('{slug}', [\App\Http\Controllers\PublicSlugController::class, 'resolve'])
    ->name('treatments.show')
    ->where('slug', '[a-z0-9\-]+');
