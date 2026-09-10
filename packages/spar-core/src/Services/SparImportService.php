<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparImportBatch;
use Zapmed\SparCore\Models\SparImportLog;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SparImportService
{
    /**
     * Identity records from a companion Drug Usage report, keyed by
     * "profile_code|dependent_code" (two-char dependent). Populated when a
     * dual-file import is run (sales extract + drug usage). Empty otherwise.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $identityMap = [];

    /**
     * Column mapping — maps CSV headers to our internal fields.
     * Update this when SPAR sends the final format.
     */
    private const COLUMN_MAP = [
        'Store Name' => 'store_name',
        'Date' => 'date',
        'Time' => 'time',
        'Document Number' => 'document_number',
        'Cashier Pharmacist Number' => 'pharmacist_number',
        'Barcode' => 'barcode',
        'Bhf' => 'bhf_code',
        'Stock Code' => 'stock_code',
        'Nappi' => 'nappi_code',
        'Item Description' => 'item_description',
        'Unit Size' => 'unit_size',
        'Schedule' => 'schedule',
        'Sales Quantity' => 'sales_quantity',
        'Sales Value Excl' => 'sales_value',
        'Vat' => 'vat',
        'Discount Percentage' => 'discount_percentage',
        'Discount Amount' => 'discount_amount',
        'Cost' => 'cost',
        'GP' => 'gp',
        'Dispensing Fee' => 'dispensing_fee',
        'Department' => 'department',
        'Supplier' => 'supplier',
        'Brand Name' => 'brand_name',
        'TransactionType' => 'transaction_type',
        'Medical Aid' => 'medical_aid',
        'Medical Aid Option' => 'medical_aid_option',
        'Client Name' => 'client_name',
        'Profile Code' => 'profile_code',
        'Dependent Code' => 'dependent_code',
        'Dependent Relation' => 'dependent_relation',
        'Repeats' => 'repeats',
        'Repeat number' => 'repeat_number',
        'Claimed' => 'claimed',
        'Levy' => 'levy',
        'Age' => 'age',
        'Gender' => 'gender',
        'Doctor' => 'doctor',
        'Doctor BHF' => 'doctor_bhf',
        'Script Number' => 'script_number',

        // Optional contact columns — present only when SPAR ships a complete
        // export (onboarding_mode = 'import', spec FR-6.2). Absent in the
        // current sales-transaction extract (pharmacist_capture mode).
        'First Name' => 'first_name',
        'Last Name' => 'last_name',
        'Surname' => 'last_name',
        'Cellphone' => 'cellphone',
        'Cell' => 'cellphone',
        'Mobile' => 'cellphone',
        'Mobile Number' => 'cellphone',
        'Email' => 'email',
        'Email Address' => 'email',
    ];

    /**
     * Import BOTH SPAR files together: the sales extract (dispense history) and
     * the Drug Usage report (patient identity + contact). The two are linked on
     * (profile_code, dependent_code) — the fields SPAR is adding to the report.
     *
     * The Drug Usage file is parsed first into an identity map; the sales CSV is
     * then imported and each patient is enriched with their matched identity.
     */
    public function importPair(
        string $salesCsvPath,
        string $drugUsagePath,
        int $importedBy = null,
        string $source = 'manual'
    ): SparImportBatch {
        try {
            $this->identityMap = (new DrugUsageParser())->parse($drugUsagePath);
        } catch (\Throwable $e) {
            // Identity file is a hard requirement for the paired import — fail
            // loudly rather than silently importing dispenses with no contacts.
            Log::error('SPAR drug-usage parse failed', ['error' => $e->getMessage()]);
            // Still delete the uploaded identity file (may contain PHI).
            if (is_file($drugUsagePath)) {
                @unlink($drugUsagePath);
            }
            throw $e;
        }

        try {
            return $this->importFile($salesCsvPath, $importedBy, $source);
        } finally {
            // SECURITY: the Drug Usage report holds names/phones/addresses (PHI).
            if (is_file($drugUsagePath)) {
                @unlink($drugUsagePath);
            }
            $this->identityMap = [];
        }
    }

    /**
     * Import a CSV file.
     */
    public function importFile(string $filePath, int $importedBy = null, string $source = 'manual'): SparImportBatch
    {
        $batch = SparImportBatch::create([
            'filename' => basename($filePath),
            'source' => $source,
            'imported_by' => $importedBy,
            'status' => 'pending',
        ]);

        try {
            $batch->markProcessing();

            $rows = $this->parseCSV($filePath);
            $batch->update(['records_total' => count($rows)]);

            // Group rows by profile_code + dependent_code + store so each
            // household MEMBER (principal + each dependant) becomes its own
            // patient. Grouping on profile alone would collapse dependants into
            // the principal (they share a profile code).
            $grouped = collect($rows)->groupBy(function ($row) {
                return ($row['profile_code'] ?? '')
                    . '|' . ($row['dependent_code'] ?? '')
                    . '|' . ($row['store_name'] ?? '');
            });

            foreach ($grouped as $key => $patientRows) {
                try {
                    $this->processPatientGroup($batch, $patientRows->toArray());
                } catch (\Exception $e) {
                    $firstRow = $patientRows->first();
                    SparImportLog::create([
                        'batch_id' => $batch->id,
                        'row_number' => 0,
                        'profile_code' => $firstRow['profile_code'] ?? null,
                        'store_name' => $firstRow['store_name'] ?? null,
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'raw_data' => $firstRow,
                    ]);
                    $batch->incrementCounter('records_failed');
                }
            }

            $batch->markCompleted([
                'total_patients' => $grouped->count(),
                'pharmacies_found' => $grouped->pluck('0.store_name')->unique()->count(),
            ]);

            // SECURITY: Delete the CSV file after processing (contains PHI)
            if (file_exists($filePath)) {
                unlink($filePath);
            }

        } catch (\Exception $e) {
            Log::error('SPAR import failed', ['batch' => $batch->id, 'error' => $e->getMessage()]);
            $batch->markFailed($e->getMessage());

            // SECURITY: Delete the CSV even on failure
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        return $batch;
    }

    /**
     * Process a group of rows belonging to the same patient at the same pharmacy.
     */
    private function processPatientGroup(SparImportBatch $batch, array $rows): void
    {
        $firstRow = $rows[0];
        $profileCode = trim($firstRow['profile_code'] ?? '');
        $storeName = trim($firstRow['store_name'] ?? '');
        $bhfCode = trim($firstRow['bhf_code'] ?? '');

        if (empty($profileCode)) {
            SparImportLog::create([
                'batch_id' => $batch->id,
                'row_number' => 0,
                'status' => 'skipped',
                'error_message' => 'No profile code',
                'raw_data' => $firstRow,
            ]);
            $batch->incrementCounter('records_skipped');
            return;
        }

        DB::transaction(function () use ($batch, $rows, $firstRow, $profileCode, $storeName, $bhfCode) {
            // Find or create pharmacy
            $pharmacy = $this->findOrCreatePharmacy($storeName, $bhfCode);
            $dependentCode = trim($firstRow['dependent_code'] ?? '0');

            // NATIONAL identity resolution (spec FR-2): match by the blind index
            // of the profile code + dependent code, INDEPENDENT of pharmacy, so a
            // patient who fills at a second SPAR store is recognised as the SAME
            // person rather than duplicated. profile_code is encrypted, so we
            // match on its keyed hash.
            $profileHash = SparPatient::blindIndex($profileCode, 'profile');
            $patient = SparPatient::where('profile_code_hash', $profileHash)
                ->where('dependent_code', $dependentCode)
                ->first();

            $wasCreated = false;
            if (!$patient) {
                $patient = SparPatient::create([
                    'spar_pharmacy_id' => $pharmacy->id, // home / most-recent pharmacy
                    'onboarding_pharmacy_id' => $pharmacy->id, // first store seen (set once)
                    'captured_at' => now(),
                    'profile_code' => $profileCode,
                    'dependent_code' => $dependentCode,
                    'dependent_relation' => trim($firstRow['dependent_relation'] ?? null),
                    'medical_aid_name' => trim($firstRow['medical_aid'] ?? null),
                    'medical_aid_option' => trim($firstRow['medical_aid_option'] ?? null),
                    'is_primary_member' => ($dependentCode === '0' || $dependentCode === '00'),
                    'metadata' => [
                        'age' => $firstRow['age'] ?? null,
                        'gender' => $firstRow['gender'] ?? null,
                        'client_name' => $firstRow['client_name'] ?? null,
                    ],
                ]);
                $wasCreated = true;
            } else {
                // Existing national patient — refresh home/most-recent pharmacy.
                if ($patient->spar_pharmacy_id !== $pharmacy->id) {
                    $patient->update(['spar_pharmacy_id' => $pharmacy->id]);
                }
            }

            // Phone secondary verification (spec FR-3): the profile matched, but
            // confirm the phone agrees. If the row carries a phone whose hash
            // differs from a NON-EMPTY stored phone, do NOT silently merge —
            // attach the dispense but flag the patient for human review.
            $this->verifyPhone($patient, $firstRow, $wasCreated);

            // Onboarding mode A ('import'): the export carries contact details,
            // so populate the SPAR-owned identity from the file (spec FR-6.2).
            // Mode B ('pharmacist_capture'): seed medication history only; the
            // pharmacist captures identity later (spec FR-6.3). We never
            // overwrite an already-captured contact field with a blank.
            if (config('spar.onboarding_mode') === 'import') {
                $this->applyImportedIdentity($patient, $firstRow);
            }

            // Dual-file import: enrich identity from the matched Drug Usage
            // record, linked on (profile_code, dependent_code). This is where
            // the sales extract (no contact) meets the report (name/phone/etc).
            $this->applyDrugUsageIdentity($patient, $profileCode, trim($firstRow['dependent_code'] ?? '0'));

            // Recalculate onboarding lifecycle from identity + consent state.
            $patient->refreshOnboardingStatus();

            // Group rows by script/document to detect distinct prescriptions
            $byScript = collect($rows)->groupBy(function ($row) {
                return trim($row['script_number'] ?? $row['document_number'] ?? 'unknown');
            });

            foreach ($byScript as $scriptNumber => $scriptRows) {
                $this->processScriptGroup($batch, $patient, $pharmacy, $scriptNumber, $scriptRows->toArray());
            }

            SparImportLog::create([
                'batch_id' => $batch->id,
                'row_number' => 0,
                'profile_code' => $profileCode,
                'store_name' => $storeName,
                'status' => $wasCreated ? 'created' : 'updated',
                'action' => $wasCreated ? 'patient_created' : 'patient_updated',
            ]);

            $batch->incrementCounter($wasCreated ? 'records_created' : 'records_updated');
        });
    }

    /**
     * Populate SPAR-owned identity from an imported row (onboarding_mode =
     * 'import' only). Splits Client Name as a fallback when discrete
     * First/Last columns are absent. Never overwrites an existing captured
     * value with a blank, so re-imports don't wipe pharmacist-entered data.
     */
    private function applyImportedIdentity(SparPatient $patient, array $row): void
    {
        $firstName = trim($row['first_name'] ?? '');
        $lastName = trim($row['last_name'] ?? '');

        // Fallback: derive from "Client Name" if discrete columns are missing.
        if ($firstName === '' && $lastName === '' && !empty($row['client_name'])) {
            $parts = preg_split('/\s+/', trim($row['client_name']), 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';
        }

        $updates = [];
        if ($firstName !== '' && empty($patient->first_name)) {
            $updates['first_name'] = $firstName;
        }
        if ($lastName !== '' && empty($patient->last_name)) {
            $updates['last_name'] = $lastName;
        }

        $cellphone = trim($row['cellphone'] ?? '');
        if ($cellphone !== '' && empty($patient->cellphone)) {
            $updates['cellphone'] = $cellphone;
        }

        $email = trim($row['email'] ?? '');
        if ($email !== '' && empty($patient->email)) {
            $updates['email'] = $email;
        }

        if (!empty($updates)) {
            $patient->update($updates);
        }
    }

    /**
     * Phone secondary verification (spec FR-3, national identity). The profile
     * code already matched; confirm the incoming phone agrees. Sources the phone
     * from the paired Drug Usage identity (where contact lives) or the sales row.
     * If a NON-EMPTY stored phone disagrees with a non-empty incoming phone, flag
     * the patient for review rather than silently trusting the match. A matching
     * phone (or one side blank) is fine — no action.
     */
    private function verifyPhone(SparPatient $patient, array $firstRow, bool $wasCreated): void
    {
        if ($wasCreated) {
            return; // brand-new patient — nothing to reconcile against
        }

        $incoming = $this->incomingPhoneFor($patient, $firstRow);
        if ($incoming === '') {
            return; // no incoming phone to compare
        }

        $stored = trim((string) $patient->cellphone);
        if ($stored === '') {
            return; // stored side blank — applyDrugUsageIdentity/applyImportedIdentity will fill it
        }

        if (SparPatient::blindIndex($incoming, 'phone') !== SparPatient::blindIndex($stored, 'phone')) {
            $patient->flagIdentityReview(
                'Profile matched but incoming phone differs from stored phone during import.'
            );
        }
    }

    /**
     * Resolve the phone the import is bringing in for this patient — from the
     * matched Drug Usage identity (paired import) first, then the sales row.
     */
    private function incomingPhoneFor(SparPatient $patient, array $firstRow): string
    {
        if (!empty($this->identityMap)) {
            $identity = $this->matchIdentity($patient->profile_code, (string) $patient->dependent_code);
            if ($identity && !empty($identity['cellphone'])) {
                return trim((string) $identity['cellphone']);
            }
        }

        return trim((string) ($firstRow['cellphone'] ?? ''));
    }

    /**
     * Enrich a patient's SPAR-owned identity from the companion Drug Usage
     * report, matched on (profile_code, dependent_code). No-op when no identity
     * map is loaded (single-file import) or no row matches this patient.
     *
     * Never overwrites an already-populated field with imported data, so
     * pharmacist-captured contact details always win over the report.
     */
    private function applyDrugUsageIdentity(SparPatient $patient, string $profileCode, string $dependentCode): void
    {
        if (empty($this->identityMap)) {
            return;
        }

        $identity = $this->matchIdentity($profileCode, $dependentCode);
        if ($identity === null) {
            return;
        }

        $updates = [];

        $first = trim((string) ($identity['first_name'] ?? ''));
        if ($first !== '' && empty($patient->first_name)) {
            $updates['first_name'] = $first;
        }

        $last = trim((string) ($identity['last_name'] ?? ''));
        if ($last !== '' && empty($patient->last_name)) {
            $updates['last_name'] = $last;
        }

        $cell = trim((string) ($identity['cellphone'] ?? ''));
        if ($cell !== '' && empty($patient->cellphone)) {
            $updates['cellphone'] = $cell;
        }

        $email = trim((string) ($identity['email'] ?? ''));
        if ($email !== '' && empty($patient->email)) {
            $updates['email'] = $email;
        }

        // Address + member number live in metadata (no dedicated columns).
        $meta = $patient->metadata ?? [];
        if (!empty($identity['address']) && empty($meta['address'])) {
            $meta['address'] = trim((string) $identity['address']);
        }
        if (!empty($identity['member_number']) && empty($meta['member_number'])) {
            $meta['member_number'] = trim((string) $identity['member_number']);
        }
        if (!empty($identity['medical_aid_number']) && empty($meta['medical_aid_number'])) {
            $meta['medical_aid_number'] = trim((string) $identity['medical_aid_number']);
        }
        if ($meta !== ($patient->metadata ?? [])) {
            $updates['metadata'] = $meta;
        }

        if (!empty($updates)) {
            $patient->update($updates);
        }
    }

    /**
     * Look up an identity by (profile, dependent), tolerating dependent-code
     * format differences ('0' vs '00'). Matches ONLY the exact member — a
     * dependent is never given the principal's identity (dependents roll up
     * under the principal for viewing, but keep their own contact state).
     *
     * @return array<string, mixed>|null
     */
    private function matchIdentity(string $profileCode, string $dependentCode): ?array
    {
        $profile = trim($profileCode);
        $dep = trim($dependentCode);
        $depPadded = ctype_digit($dep) ? str_pad($dep, 2, '0', STR_PAD_LEFT) : ($dep === '' ? '00' : $dep);

        foreach (array_unique([$dep, $depPadded]) as $candidate) {
            $key = $profile . '|' . $candidate;
            if (isset($this->identityMap[$key])) {
                return $this->identityMap[$key];
            }
        }

        return null;
    }
    private function processScriptGroup(
        SparImportBatch $batch,
        SparPatient $patient,
        SparPharmacy $pharmacy,
        string $scriptNumber,
        array $rows
    ): void {
        $firstRow = $rows[0];
        $date = $this->parseDate($firstRow['date'] ?? '');

        // Build medications array from all rows in this script
        $medications = collect($rows)->map(function ($row) {
            return [
                'name' => trim($row['item_description'] ?? ''),
                'nappi_code' => trim($row['nappi_code'] ?? ''),
                'quantity' => (float) ($row['sales_quantity'] ?? 0),
                'value' => (int) round((float) ($row['sales_value'] ?? 0) * 100),
                'schedule' => $row['schedule'] ?? null,
                'supplier' => $row['supplier'] ?? null,
                'brand_name' => $row['brand_name'] ?? null,
            ];
        })->toArray();

        // Find existing journey for this script or create new one
        $journey = SparPrescriptionJourney::where('spar_patient_id', $patient->id)
            ->where('script_number', $scriptNumber)
            ->where('status', 'active')
            ->first();

        if (!$journey) {
            $repeats = (int) ($firstRow['repeats'] ?? 6);
            $journey = SparPrescriptionJourney::create([
                'spar_patient_id' => $patient->id,
                'spar_pharmacy_id' => $pharmacy->id,
                'script_number' => $scriptNumber,
                'status' => 'active',
                'total_dispenses' => $repeats > 0 ? $repeats : 6,
                'dispenses_completed' => 0,
                'start_date' => $date ?? now(),
                'next_dispense_date' => ($date ?? now())->copy()->addMonth(),
                'renewal_due_date' => ($date ?? now())->copy()->addMonths($repeats > 0 ? $repeats : 6),
                'doctor_name' => trim($firstRow['doctor'] ?? ''),
                'doctor_bhf' => trim($firstRow['doctor_bhf'] ?? ''),
                'medications' => $medications,
            ]);
        }

        // Record this dispense event
        $repeatNumber = (int) ($firstRow['repeat_number'] ?? ($journey->dispenses_completed + 1));
        $totalValue = collect($rows)->sum(function ($row) {
            return (int) round((float) ($row['sales_value'] ?? 0) * 100);
        });

        // Check if this dispense already exists
        $existingDispense = SparDispenseRecord::where('journey_id', $journey->id)
            ->where('document_number', trim($firstRow['document_number'] ?? ''))
            ->first();

        if (!$existingDispense && $date) {
            SparDispenseRecord::create([
                'journey_id' => $journey->id,
                'spar_patient_id' => $patient->id,
                'dispense_number' => $repeatNumber,
                'status' => 'collected',
                'due_date' => $date,
                'completed_at' => $date,
                'fulfillment_type' => 'collection',
                'document_number' => trim($firstRow['document_number'] ?? null),
                'sales_value' => $totalValue,
                'items' => $medications,
            ]);

            $journey->increment('dispenses_completed');

            if ($journey->isFinalDispense()) {
                $journey->update(['status' => 'renewal_due']);
            } else {
                $journey->update(['next_dispense_date' => $date->copy()->addMonth()]);
            }
        }
    }

    /**
     * Find or create a SPAR pharmacy by store name or BHF code.
     */
    private function findOrCreatePharmacy(string $storeName, string $bhfCode): SparPharmacy
    {
        // Try BHF code first (more reliable)
        if (!empty($bhfCode)) {
            $pharmacy = SparPharmacy::where('bhf_code', $bhfCode)->first();
            if ($pharmacy) return $pharmacy;
        }

        // Fall back to store name
        $pharmacy = SparPharmacy::where('name', $storeName)->first();
        if ($pharmacy) return $pharmacy;

        // Create new pharmacy
        return SparPharmacy::create([
            'name' => $storeName,
            'spar_store_id' => $bhfCode ?: Str::slug($storeName),
            'bhf_code' => $bhfCode ?: null,
            'is_active' => true,
        ]);
    }

    /**
     * Parse the CSV file into an array of mapped rows.
     */
    private function parseCSV(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Import file not found.');
        }

        $fileSize = filesize($filePath);
        if ($fileSize > 50 * 1024 * 1024) { // 50MB max
            throw new \RuntimeException('File too large. Maximum 50MB allowed.');
        }

        $content = file_get_contents($filePath);

        // Strip a UTF-8 BOM if present — otherwise the first header (Store Name)
        // arrives as "\uFEFFStore Name" and fails to map. The real SPAR export
        // is UTF-8 with a BOM, so this matters in production, not just tests.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // SECURITY: Reject files with potential injection patterns
        if (preg_match('/(<\?php|<script|eval\s*\(|exec\s*\(|system\s*\()/i', substr($content, 0, 10000))) {
            throw new \RuntimeException('File rejected: potentially malicious content detected.');
        }

        // Detect delimiter (pipe or comma)
        $firstLine = strtok($content, "\n");
        $delimiter = str_contains($firstLine, '|') ? '|' : ',';

        $lines = str_getcsv($content, "\n");
        $headers = str_getcsv(array_shift($lines), $delimiter);

        // Clean headers
        $headers = array_map('trim', $headers);

        // Validate we have expected columns
        if (count($headers) < 5) {
            throw new \RuntimeException('Invalid file format: too few columns detected.');
        }

        $rows = [];
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $values = str_getcsv($line, $delimiter);

            // Map to our internal field names
            $mapped = [];
            foreach ($headers as $i => $header) {
                $internalField = self::COLUMN_MAP[$header] ?? Str::snake($header);
                // SECURITY: Strip any null bytes and control characters
                $value = trim($values[$i] ?? '');
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
                $mapped[$internalField] = $value;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * Parse date from SPAR format.
     */
    private function parseDate(string $dateStr): ?\Carbon\Carbon
    {
        if (empty($dateStr)) return null;

        try {
            return \Carbon\Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }
}
