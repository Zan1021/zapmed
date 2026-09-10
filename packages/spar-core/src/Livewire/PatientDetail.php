<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparPatient;
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

    public function render()
    {
        return view('spar::livewire.patient-detail')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
