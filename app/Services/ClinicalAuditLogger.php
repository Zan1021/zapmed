<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Records READ access to clinical records (consultations, prescriptions, and the
 * documents rendered from them). POPIA's accountability principle + HPCSA
 * record-keeping expect us to be able to answer "who viewed this patient's
 * clinical record, and when" — not just who wrote it. Write-only audit logs
 * cannot answer that; this fills the gap.
 *
 * Uses the dedicated `clinical_audit` log channel (365-day retention), mirroring
 * the proven `spar_audit` pattern.
 */
class ClinicalAuditLogger
{
    /**
     * Log a read/access event against a clinical record.
     *
     * @param string $recordType e.g. 'prescription', 'sick_note', 'medical_certificate', 'consultation'
     * @param int    $recordId   the record's primary key
     * @param int|null $patientId the patient the record belongs to (subject of the data)
     * @param string $action     e.g. 'view', 'download'
     */
    public function logRead(string $recordType, int $recordId, ?int $patientId, string $action = 'view'): void
    {
        $actor = auth()->user();

        Log::channel('clinical_audit')->info("clinical_read: {$action} {$recordType} #{$recordId}", [
            'action' => $action,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'patient_id' => $patientId,
            'accessed_by_id' => $actor?->id,
            'accessed_by_email' => $actor?->email,
            'accessed_by_role' => $actor?->role?->value,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
