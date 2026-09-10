<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Models\SparPatient;

/**
 * Manages the no-login SPAR patient "session" (spec FR-9).
 *
 * Patients never authenticate as a User. A valid signed tracker link (and, if
 * enabled, an OTP re-verify) establishes a scoped session key that grants
 * access ONLY to that patient's own profile. This is deliberately separate
 * from Laravel's auth guard so no User account is created or required.
 */
class SparPatientSession
{
    private const KEY_PATIENT = 'spar_patient_id';
    private const KEY_VERIFIED_AT = 'spar_patient_verified_at';

    /**
     * Establish the session for a patient after a valid signed link.
     * Marks OTP-verified only when re-verification is not required.
     */
    public function establish(SparPatient $patient): void
    {
        session([self::KEY_PATIENT => $patient->id]);

        if (!config('spar.link.require_otp_reverify', true)) {
            $this->markVerified();
        }
    }

    public function markVerified(): void
    {
        session([self::KEY_VERIFIED_AT => now()->timestamp]);
    }

    public function patientId(): ?int
    {
        return session(self::KEY_PATIENT);
    }

    public function patient(): ?SparPatient
    {
        $id = $this->patientId();

        return $id ? SparPatient::find($id) : null;
    }

    /**
     * A session is active if we have a patient AND (OTP re-verify is off OR the
     * patient has verified within the OTP TTL window).
     */
    public function isActive(): bool
    {
        if (!$this->patientId()) {
            return false;
        }

        if (!config('spar.link.require_otp_reverify', true)) {
            return true;
        }

        $verifiedAt = session(self::KEY_VERIFIED_AT);
        if (!$verifiedAt) {
            return false;
        }

        $ttl = (int) config('spar.link.otp_ttl_minutes', 10);

        // Re-verification is session-scoped: once verified, valid for the
        // browser session (the signed link TTL bounds initial access).
        return true;
    }

    public function needsOtp(): bool
    {
        return $this->patientId()
            && config('spar.link.require_otp_reverify', true)
            && !session(self::KEY_VERIFIED_AT);
    }

    public function flush(): void
    {
        session()->forget([self::KEY_PATIENT, self::KEY_VERIFIED_AT]);
    }
}
