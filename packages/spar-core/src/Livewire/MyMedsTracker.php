<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\OtpSender;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparPatientView;
use Zapmed\SparCore\Services\SparOrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * No-login SPAR patient tracker (spec FR-7, FR-8, FR-9).
 *
 * Flow: valid signed link establishes a scoped SPAR patient session ->
 *   [otp]     optional OTP re-verify (forwarded-link protection)
 *   [consent] consent gate — NO PHI shown until granted (hard stop)
 *   [dashboard] medication tracker (rolls up dependants under the profile)
 *
 * Never authenticates a User. All access is scoped to the session patient.
 */
class MyMedsTracker extends Component
{
    use LogsSparActivity;

    public string $step = 'consent'; // otp | consent | dashboard
    public string $otp = '';
    public string $error = '';
    public bool $consentAccepted = false;

    // delivery form (carried over from MyMedsDashboard)
    public bool $showDeliveryForm = false;
    public string $deliveryAddress = '';
    public string $deliveryCity = '';
    public string $deliveryPostalCode = '';
    public string $deliveryPhone = '';

    // "Order next meds" flow (FR-C1)
    public ?int $orderingJourneyId = null;   // which journey's order form is open
    public string $orderMode = '';           // collect_pay_now | deliver_pay_now | collect_pay_store
    public string $orderPlaced = '';         // reference of the last placed order (confirmation)

    private function session(): SparPatientSession
    {
        return app(SparPatientSession::class);
    }

    public function mount(): void
    {
        $session = $this->session();

        if (!$session->patientId()) {
            abort(403, 'Invalid or expired tracker link.');
        }

        if ($session->needsOtp()) {
            $this->step = 'otp';
            $this->dispatchOtp();
            return;
        }

        $this->resolveStepFromConsent();
    }

    private function resolveStepFromConsent(): void
    {
        $patient = $this->session()->patient();
        $this->step = ($patient && $patient->hasConsented()) ? 'dashboard' : 'consent';
    }

    // ---- OTP re-verify -----------------------------------------------------

    private function otpCacheKey(): string
    {
        return 'spar_track_otp_' . $this->session()->patientId();
    }

    private function dispatchOtp(): void
    {
        $patient = $this->session()->patient();
        if (!$patient || !$patient->isContactable()) {
            $this->error = 'We could not reach you to verify. Please contact your SPAR pharmacy.';
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->otpCacheKey(), ['code' => $code, 'attempts' => 0],
            now()->addMinutes((int) config('spar.link.otp_ttl_minutes', 10)));

        // Consent is not yet granted here, so send the OTP directly through the
        // host OtpSender transport rather than the consent-gated dispatcher.
        if ($phone = $patient->primaryPhone()) {
            app(OtpSender::class)->sendOtp($phone, $code);
        }

        if (app()->environment('local', 'staging')) {
            session()->flash('dev_otp', $code);
        }
    }

    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string|size:6']);
        $cached = Cache::get($this->otpCacheKey());

        if (!$cached) {
            $this->error = 'Code expired. Please reopen your link.';
            return;
        }
        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget($this->otpCacheKey());
            $this->error = 'Too many attempts. Please reopen your link.';
            return;
        }

        $cached['attempts']++;
        Cache::put($this->otpCacheKey(), $cached, now()->addMinutes((int) config('spar.link.otp_ttl_minutes', 10)));

        if ($cached['code'] !== $this->otp) {
            $this->error = 'Invalid code. Please try again.';
            return;
        }

        Cache::forget($this->otpCacheKey());
        $this->session()->markVerified();
        $this->error = '';
        $this->otp = '';
        $this->resolveStepFromConsent();
    }

    // ---- Consent gate (hard stop) -----------------------------------------

    public function grantConsent(): void
    {
        if (!$this->consentAccepted) {
            $this->error = 'Please tick the box to give consent before continuing.';
            return;
        }

        $patient = $this->session()->patient();
        if (!$patient) {
            abort(403);
        }

        $patient->optIn('web', [
            'source' => 'patient',
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $this->logSparActivity('consent_granted', 'Patient granted consent via tracker', [
            'spar_patient_id' => $patient->id,
            'channel' => 'web',
        ]);

        $this->error = '';
        $this->step = 'dashboard';
    }

    public function declineConsent(): void
    {
        $patient = $this->session()->patient();
        if ($patient) {
            $patient->optOut([
                'source' => 'patient',
                'channel' => 'web',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
            $this->logSparActivity('consent_declined', 'Patient declined consent via tracker', [
                'spar_patient_id' => $patient->id,
            ]);
        }

        $this->session()->flush();
        $this->step = 'consent';
        session()->flash('declined', true);
    }

    // ---- Dashboard data (scoped to the session patient + dependants) -------

    /**
     * The primary member for this session (dependants roll up under them,
     * spec FR-8). Resolved via the shared SparPatientView (one source of truth
     * with the staff mirror).
     */
    public function getSparPatientProperty(): ?SparPatient
    {
        $patient = $this->session()->patient();
        if (!$patient) {
            return null;
        }

        return app(SparPatientView::class)->primary($patient);
    }

    /**
     * All patients under this profile (primary + dependants) for the roll-up.
     */
    public function getProfileMembersProperty()
    {
        $patient = $this->session()->patient();
        if (!$patient) {
            return collect();
        }

        return app(SparPatientView::class)->members($patient);
    }

    /**
     * Active journeys for the PRIMARY member ONLY (spec FR-D / D1). Dependants'
     * medication is private to them — the primary sees their dependants listed
     * (getDependantsProperty) but never their scripts.
     */
    public function getJourneysProperty()
    {
        $patient = $this->session()->patient();
        if (!$patient) {
            return collect();
        }

        return app(SparPatientView::class)->selfJourneys($patient);
    }

    /**
     * Dependants under this profile, for the "listed but private" panel — names
     * only, no medication data (D1: display option (b), masked placeholder).
     */
    public function getDependantsProperty()
    {
        $patient = $this->session()->patient();
        if (!$patient) {
            return collect();
        }

        return app(SparPatientView::class)->dependants($patient);
    }

    public function getRenewalDueProperty()
    {
        return $this->journeys->firstWhere('status', 'renewal_due');
    }

    // ---- "Order next meds" (FR-C1) ----------------------------------------

    /**
     * The latest still-open order per journey, keyed by journey id. An order is
     * "open" while it's requested / preparing / ready (not completed or
     * cancelled). Orders link to a journey through their dispense record, so we
     * resolve journey_id via the dispense. Drives the per-card status badge so a
     * patient who has already ordered sees the order's progress instead of the
     * "Order next meds" button (and can't double-order the same script).
     *
     * @return \Illuminate\Support\Collection<int, SparOrder>  journeyId => order
     */
    public function getActiveOrdersByJourneyProperty()
    {
        $journeyIds = $this->journeys->pluck('id')->all();
        if (empty($journeyIds)) {
            return collect();
        }

        // dispense_record_id -> journey_id map for this profile's journeys.
        $dispenseToJourney = SparDispenseRecord::whereIn('journey_id', $journeyIds)
            ->pluck('journey_id', 'id');

        if ($dispenseToJourney->isEmpty()) {
            return collect();
        }

        return SparOrder::whereIn('dispense_record_id', $dispenseToJourney->keys())
            ->whereIn('status', ['requested', 'preparing', 'ready'])
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (SparOrder $o) => (int) $dispenseToJourney[$o->dispense_record_id])
            ->map(fn ($orders) => $orders->first()); // latest open order per journey
    }

    /**
     * Human label + tailwind classes for an order's current status, for the
     * per-journey badge on the tracker card.
     *
     * @return array{label: string, classes: string}
     */
    public function orderStatusBadge(SparOrder $order): array
    {
        return match ($order->status) {
            'requested' => ['label' => 'Order received', 'classes' => 'border-blue-200 bg-blue-50 text-blue-700'],
            'preparing' => ['label' => 'Being prepared', 'classes' => 'border-amber-200 bg-amber-50 text-amber-700'],
            'ready' => ['label' => 'Ready to collect', 'classes' => 'border-green-200 bg-green-50 text-green-700'],
            default => ['label' => ucfirst((string) $order->status), 'classes' => 'border-gray-200 bg-gray-50 text-gray-700'],
        };
    }

    /**
     * Available fulfilment modes for the order dropdown. Delivery is only
     * offered when the journey's pharmacy supports it (mirrors the service
     * guard so the UI can't offer an impossible option).
     *
     * @return array<string, string>  mode => label
     */
    public function orderModesFor(SparPrescriptionJourney $journey): array
    {
        $modes = [];
        foreach (SparOrder::MODES as $mode => $config) {
            if ($config['type'] === 'delivery' && ! $journey->pharmacy?->supports_delivery) {
                continue;
            }
            $modes[$mode] = $config['label'];
        }

        return $modes;
    }

    /**
     * Open (or toggle) the order form for a specific active journey. Scoped:
     * the journey must belong to this session's profile roll-up, so a tampered
     * id can't order against someone else's script.
     */
    public function startOrder(int $journeyId): void
    {
        if (! $this->journeys->contains('id', $journeyId)) {
            abort(403);
        }

        $this->orderingJourneyId = $this->orderingJourneyId === $journeyId ? null : $journeyId;
        $this->orderMode = '';
        $this->error = '';
    }

    public function cancelOrder(): void
    {
        $this->orderingJourneyId = null;
        $this->orderMode = '';
    }

    /**
     * Place a patient order for the selected journey (FR-C1). Consent is a hard
     * gate — the dashboard step already requires it, but we re-check so this can
     * never create an order for a non-consented patient (NFR-1). Resolves the
     * journey's latest dispense as the order subject (demo-grade: the "next"
     * fill the patient is asking for).
     */
    public function placeOrder(): void
    {
        $journey = $this->journeys->firstWhere('id', $this->orderingJourneyId);
        if (! $journey) {
            abort(403);
        }

        $patient = $this->sparPatient;
        if (! $patient || ! $patient->hasConsented()) {
            $this->error = 'We need your consent before placing an order.';
            return;
        }

        if (! array_key_exists($this->orderMode, $this->orderModesFor($journey))) {
            $this->error = 'Please choose how you would like your medication.';
            return;
        }

        $dispense = $journey->dispenseRecords()->latest('id')->first();
        if (! $dispense) {
            $this->error = 'There is nothing to order on this script yet.';
            return;
        }

        $order = app(SparOrderService::class)->placePatientOrder($dispense, $this->orderMode);

        $this->logSparActivity('order_placed', 'Patient placed an order via tracker', [
            'spar_patient_id' => $patient->id,
            'order' => $order->reference,
            'mode' => $order->fulfilment_mode,
        ]);

        $this->orderPlaced = $order->reference;
        $this->orderingJourneyId = null;
        $this->orderMode = '';
        $this->error = '';
    }

    /**
     * Unread coach messages for the badge on the Health Coach button. Resolves
     * the conversation for the profile's primary member at their pharmacy
     * (same binding as PatientCoachMessages) WITHOUT creating one — a null
     * conversation (nothing sent yet) simply reads as zero.
     */
    public function getCoachUnreadCountProperty(): int
    {
        $primary = $this->sparPatient;
        if (! $primary) {
            return 0;
        }

        $pharmacyId = (int) ($primary->spar_pharmacy_id
            ?? optional($this->journeys->first())->spar_pharmacy_id);

        if (! $pharmacyId) {
            return 0;
        }

        return (int) \Zapmed\SparCore\Models\SparConversation::query()
            ->where('spar_patient_id', $primary->id)
            ->where('spar_pharmacy_id', $pharmacyId)
            ->value('patient_unread_count');
    }

    /**
     * Live promo banners for THIS patient's pharmacy group (spec — shown after
     * consent, under the logo). Records an impression for each rendered banner.
     */
    public function getBannersProperty()
    {
        $patient = $this->sparPatient;
        // Resolve the group via the patient's (home) pharmacy.
        $groupId = $patient?->pharmacy?->group_id
            ?? \Zapmed\SparCore\Models\SparPharmacy::whereKey($patient?->spar_pharmacy_id)->value('group_id');

        if (!$groupId) {
            return collect();
        }

        $banners = \Zapmed\SparCore\Models\SparBanner::forGroup((int) $groupId)
            ->liveNow()
            ->limit((int) config('spar.banners.max_per_group', 5))
            ->get();

        // Impression count (batch increment the shown banners).
        if ($banners->isNotEmpty()) {
            \Zapmed\SparCore\Models\SparBanner::whereIn('id', $banners->pluck('id'))
                ->increment('impressions');
        }

        return $banners;
    }

    public function render()
    {
        return view('spar::livewire.my-meds-tracker')
            ->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
    }
}
