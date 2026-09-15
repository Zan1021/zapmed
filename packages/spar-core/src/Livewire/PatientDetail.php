<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;
use Zapmed\SparCore\Services\SparPatientView;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Staff patient detail (spec FR-2..FR-6). A READ-ONLY mirror of what the patient
 * sees on their mobi tracker (household roll-up via the shared SparPatientView —
 * one source of truth), plus a staff-only provenance panel (dependants,
 * onboarding pharmacy + pharmacist, consent trail, review flag, contact, and the
 * patient's signed mobi tracker URL to copy/resend).
 *
 * NOT a "login as patient": staff cannot act as the patient here (no consent
 * grant, no delivery request). Scope-gated + every open is audit-logged.
 */
class PatientDetail extends Component
{
    use LogsSparActivity;

    public int $patientId;
    public string $tab = 'overview'; // overview | dependants

    public function mount(SparPatient $patient): void
    {
        // Scope gate (national-aware): only a patient the current actor may see
        // resolves; anything else is out of scope → 404.
        $visible = SparPatient::visibleToCurrentActor()->whereKey($patient->id)->first();
        abort_unless($visible !== null, 404);

        $this->patientId = $patient->id;

        // POPIA: viewing a patient's full record is a PHI read — audit it.
        $this->logSparActivity('patient_access', 'Staff viewed patient detail', [
            'spar_patient_id' => $patient->id,
        ]);
    }

    private function view(): SparPatientView
    {
        return app(SparPatientView::class);
    }

    private function subject(): SparPatient
    {
        return SparPatient::findOrFail($this->patientId);
    }

    /** The primary member of the profile (the mirror resolves to them). */
    public function getPatientProperty(): SparPatient
    {
        return $this->view()->primary($this->subject());
    }

    public function getDependantsProperty()
    {
        return $this->view()->dependants($this->subject());
    }

    public function getJourneysProperty()
    {
        return $this->view()->journeys($this->subject());
    }

    public function getRenewalDueProperty()
    {
        return $this->view()->renewalDue($this->subject());
    }

    public function getPastJourneysProperty()
    {
        return $this->view()->pastJourneys($this->subject());
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['overview', 'dependants'], true) ? $tab : 'overview';
    }

    public function getHistoryProperty()
    {
        return $this->view()->history($this->subject());
    }

    /** Full consent audit trail for the primary member. */
    public function getConsentTrailProperty()
    {
        return SparConsent::where('spar_patient_id', $this->patient->id)
            ->latest()
            ->get();
    }

    /** Display name of the pharmacist who onboarded them (host-resolved). */
    public function getCapturedByNameProperty(): ?string
    {
        $id = $this->patient->captured_by_id;
        if (!$id) {
            return null;
        }

        // Resolve host-side WITHOUT the package naming a host user model
        // (AC-3/AC-15). Host supplies a resolver via config('spar.staff_name_resolver').
        $resolver = config('spar.staff_name_resolver');
        if (is_callable($resolver)) {
            return $resolver($id);
        }
        if (is_string($resolver) && class_exists($resolver) && method_exists($resolver, 'name')) {
            return app($resolver)->name($id);
        }

        return "Staff #{$id}";
    }

    /**
     * The patient's signed mobi tracker URL (spec: staff can copy/resend it).
     * Same link the reminder/capture flow sends. Bound to the PRIMARY member.
     */
    public function getMobiUrlProperty(): string
    {
        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);

        return URL::temporarySignedRoute(
            'spar.track',
            now()->addMinutes($ttl),
            ['patient' => $this->patient->id]
        );
    }

    /**
     * Send the WhatsApp opt-in (onboarding_consent) message with the signed mobi
     * tracker link to the primary member's cellphone.
     *
     * WHY THIS BYPASSES MessagingDispatcher: the dispatcher refuses to message a
     * patient who has not consented (POPIA guard). The opt-in message is the very
     * thing that REQUESTS consent, so it must be sent to a not-yet-consented
     * patient. We therefore call the WhatsAppChannel directly with the approved
     * `onboarding_consent` template — a pre-approved template send is permitted
     * outside the 24h session window and is how consent is solicited.
     *
     * Delivery mode follows config: 'log' driver records the payload (safe demo);
     * 'cloud_api' sends for real once the token is set. Either way the staff
     * action is audit-logged (POPIA) and the outcome surfaced to the user.
     */
    public function sendOptIn(): void
    {
        $whatsapp = app(WhatsAppChannel::class);
        $patient = $this->view()->primary($this->subject());

        $phone = $patient->primaryPhone();
        if (empty($phone)) {
            session()->flash('optin_error', 'No cellphone on file for this patient — cannot send the WhatsApp opt-in.');

            return;
        }

        if (! (bool) config('spar.whatsapp.enabled', false)) {
            session()->flash('optin_error', 'WhatsApp channel is disabled (SPAR_WHATSAPP_ENABLED=false).');

            return;
        }

        // Direct template send: onboarding_consent + signed mobi link. {{1}} is
        // the patient's first name (per the approved template body).
        $sent = $whatsapp->send($patient, [
            'template' => 'onboarding_consent',
            'vars' => [$patient->first_name ?: 'there'],
            'body' => 'Welcome to SPAR Pharmacy — tap the link to view your medication tracker and give consent.',
            'link' => $this->mobiUrl,
        ]);

        // POPIA: sending a patient a message is an activity on their record.
        $this->logSparActivity('optin_sent', 'Staff sent WhatsApp opt-in', [
            'spar_patient_id' => $patient->id,
            'channel' => 'whatsapp',
            'driver' => config('spar.whatsapp.driver'),
            'result' => $sent ? 'accepted' : 'failed',
        ]);

        if ($sent) {
            $driver = config('spar.whatsapp.driver') === 'cloud_api'
                ? 'WhatsApp opt-in sent to ' . $phone . '.'
                : 'WhatsApp opt-in recorded (log driver — no live send). Set SPAR_WHATSAPP_DRIVER=cloud_api to deliver.';
            session()->flash('optin_success', $driver);
        } else {
            session()->flash('optin_error', 'WhatsApp opt-in was not delivered. Check storage/logs for the Meta error.');
        }
    }

    public function render()
    {
        return view('spar::livewire.patient-detail')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
