<?php

namespace Zapmed\SparCore\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Trait for logging SPAR pharmacy staff activity.
 * Records who did what, when, and from where.
 * Critical for POPIA compliance and security auditing.
 *
 * Host-agnostic: reads the authenticated actor via the `auth()` helper and
 * null-safe accessors. Whatever user model the host authenticates (ZapMed
 * `User` or a standalone pharmacy-staff model) supplies id/email/role/pharmacy;
 * absent fields degrade to null. No host type is referenced directly.
 */
trait LogsSparActivity
{
    /**
     * Log a SPAR activity event.
     */
    protected function logSparActivity(string $action, string $description, array $context = []): void
    {
        $user = auth()->user();

        $entry = [
            'action' => $action,
            'description' => $description,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => $user?->role?->value,
            'spar_pharmacy_id' => $user?->spar_pharmacy_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
        ];

        // Log to dedicated SPAR audit channel
        Log::channel('spar_audit')->info("{$action}: {$description}", $entry);
    }

    /**
     * Log patient data access (POPIA requirement).
     */
    protected function logPatientAccess(int $sparPatientId, string $reason = 'view'): void
    {
        $this->logSparActivity('patient_access', "Patient #{$sparPatientId} accessed", [
            'spar_patient_id' => $sparPatientId,
            'access_reason' => $reason,
        ]);
    }

    /**
     * Log an order status change.
     */
    protected function logOrderAction(int $orderId, string $action, string $fromStatus, string $toStatus): void
    {
        $this->logSparActivity('order_update', "Order #{$orderId}: {$fromStatus} → {$toStatus}", [
            'order_id' => $orderId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }

    /**
     * Log a data import event.
     */
    protected function logImportEvent(int $batchId, string $filename, string $status): void
    {
        $this->logSparActivity('data_import', "Import {$filename}: {$status}", [
            'batch_id' => $batchId,
            'filename' => $filename,
            'status' => $status,
        ]);
    }

    /**
     * Log a consent change.
     */
    protected function logConsentChange(int $sparPatientId, string $fromStatus, string $toStatus): void
    {
        $this->logSparActivity('consent_change', "Patient #{$sparPatientId} consent: {$fromStatus} → {$toStatus}", [
            'spar_patient_id' => $sparPatientId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }
}
