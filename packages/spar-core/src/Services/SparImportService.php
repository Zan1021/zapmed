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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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
     * Sales lines ignored in the current parse because they were cut off in the
     * export (no quantity and no profile code). Reported once per batch.
     */
    private int $incompleteLines = 0;

    private const NO_MAIN_MEMBER_REASON = 'No main member (dependant 0) for this household in the SPAR files. '
        . 'Confirm who the household contact is before inviting anyone.';
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
        $this->allowLongRun();

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
     * A real weekly extract (hundreds of patients, thousands of lines) takes
     * longer than PHP's default 30 second web limit, which killed the import
     * half way and left the batch stuck on "processing". Allow up to 15 minutes
     * and keep going if the browser tab is closed.
     */
    private function allowLongRun(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(900);
        }
        @ignore_user_abort(true);
    }

    /**
     * Import a CSV file.
     */
    public function importFile(string $filePath, int $importedBy = null, string $source = 'manual'): SparImportBatch
    {
        $this->allowLongRun();

        $batch = SparImportBatch::create([
            'filename' => basename($filePath),
            'source' => $source,
            'imported_by' => $importedBy,
            'status' => 'pending',
            // Initialise counters to 0 so they never render as null in the UI
            // (incrementCounter only touches the fields that actually occur).
            'records_total' => 0,
            'records_processed' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
        ]);

        try {
            $batch->markProcessing();

            $rows = $this->parseCSV($filePath);
            $batch->update(['records_total' => count($rows)]);

            if ($this->incompleteLines > 0) {
                SparImportLog::create([
                    'batch_id' => $batch->id,
                    'row_number' => 0,
                    'status' => 'skipped',
                    'error_message' => $this->incompleteLines . ' incomplete sales line(s) ignored '
                        . '(cut off in the export: no quantity, price or profile code).',
                ]);
            }

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

            // Keep age/gender current (the age on the sales line moves on each year).
            if (!$wasCreated) {
                $meta = $patient->metadata ?? [];
                foreach (['age' => 'age', 'gender' => 'gender'] as $field => $key) {
                    $v = trim((string) ($firstRow[$field] ?? ''));
                    if ($v !== '' && ($meta[$key] ?? null) !== $v) {
                        $meta[$key] = $v;
                    }
                }
                if ($meta !== ($patient->metadata ?? [])) {
                    $patient->update(['metadata' => $meta]);
                }
            }

            $this->reviewHouseholdContact($patient, $profileHash);

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
     * A household whose main member (dependant 0) is absent from the SPAR files
     * must not silently promote a dependant (often a child) to household contact.
     * Flag those dependants for staff review; clear the flag once the main
     * member turns up in a later import.
     */
    private function reviewHouseholdContact(SparPatient $patient, ?string $profileHash): void
    {
        if ($profileHash === null) {
            return;
        }

        if ($patient->is_primary_member) {
            SparPatient::where('profile_code_hash', $profileHash)
                ->where('needs_identity_review', true)
                ->where('identity_review_reason', self::NO_MAIN_MEMBER_REASON)
                ->update(['needs_identity_review' => false, 'identity_review_reason' => null]);

            return;
        }

        $hasMain = SparPatient::where('profile_code_hash', $profileHash)
            ->where('is_primary_member', true)
            ->exists();

        if (!$hasMain && !$patient->needs_identity_review) {
            $patient->flagIdentityReview(self::NO_MAIN_MEMBER_REASON);
        }
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

        $dob = trim((string) ($identity['date_of_birth'] ?? ''));
        if ($dob !== '' && empty($patient->date_of_birth)) {
            $updates['date_of_birth'] = $dob;
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
        // Source consent flag from the SPAR member extract (Y/N). Recorded for
        // reference ONLY: it never opts a patient in. Our own consent capture
        // (POPIA) stays the only thing that enables messaging. Latest value wins.
        $sourceConsent = $identity['source_consent'] ?? null;
        if (in_array($sourceConsent, ['Y', 'N'], true)) {
            $meta['source_consent'] = $sourceConsent;
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

        // A script's rows can span MULTIPLE dispensing events (fills) over time —
        // one per Document Number in a cumulative SPAR file. Sub-group by document
        // so each fill becomes its own dispense record; rows sharing a document
        // are the line-items of a single fill. (Previously only $rows[0] was used,
        // which collapsed every fill of a script into a single dispense.)
        $byDocument = collect($rows)->groupBy(function ($row) {
            return trim($row['document_number'] ?? $row['date'] ?? 'unknown');
        });

        // Journey start = earliest fill date; repeats from the first row.
        $allDates = collect($rows)
            ->map(fn ($r) => $this->parseDate($r['date'] ?? ''))
            ->filter()
            ->sort()
            ->values();
        $startDate = $allDates->first() ?? now();

        // Find existing journey for this script or create new one. A journey that
        // already hit "renewal_due" (or a finished one-off, "expired") is still
        // THIS script, so match it too: otherwise a re-import of the same file
        // opens a duplicate journey for the same script number.
        $journey = SparPrescriptionJourney::where('spar_patient_id', $patient->id)
            ->where('script_number', $scriptNumber)
            ->whereIn('status', ['active', 'renewal_due', 'expired'])
            ->first();

        // SPAR's repeat columns (confirmed against the member extract's
        // "RepeatsLeft"): "Repeats" = repeats on the original script and
        // "Repeat number" = repeats STILL LEFT after this fill. A script with R
        // repeats therefore allows R + 1 fills in total (the original + R repeats).
        [$origRepeats] = $this->repeatInfo($rows);
        $totalFills = $origRepeats !== null ? $origRepeats + 1 : 6; // blank column: legacy default of 6

        if (!$journey) {
            $journey = SparPrescriptionJourney::create([
                'spar_patient_id' => $patient->id,
                'spar_pharmacy_id' => $pharmacy->id,
                'script_number' => $scriptNumber,
                'status' => 'active',
                'total_dispenses' => $totalFills,
                'dispenses_completed' => 0,
                'start_date' => $startDate,
                'next_dispense_date' => $startDate->copy()->addMonth(),
                'renewal_due_date' => $startDate->copy()->addMonths($totalFills),
                'doctor_name' => trim($firstRow['doctor'] ?? ''),
                'doctor_bhf' => trim($firstRow['doctor_bhf'] ?? ''),
                'medications' => collect($byDocument->last())->map(fn ($row) => $this->medicationLine($row))->toArray(),
            ]);
        } elseif ($origRepeats !== null && (int) $journey->total_dispenses !== $totalFills) {
            $journey->update(['total_dispenses' => $totalFills]);
        }

        // Process each fill (document) as its own dispense, oldest first so the
        // dispense_number sequence and dates line up.
        $documents = $byDocument->sortBy(function ($docRows) {
            $d = $this->parseDate(($docRows[0]['date'] ?? ''));
            return $d ? $d->timestamp : 0;
        });

        $latestDate = null;
        $latestLeft = null;

        foreach ($documents as $docRows) {
            $docRows = $docRows->toArray();
            $docFirst = $docRows[0];
            $date = $this->parseDate($docFirst['date'] ?? '');
            if (! $date) {
                continue;
            }

            $medications = collect($docRows)->map(fn ($row) => $this->medicationLine($row))->toArray();

            $documentNumber = trim($docFirst['document_number'] ?? '');
            [, $left] = $this->repeatInfo($docRows);
            // Which fill of the script this is: total fills minus repeats still left.
            $fillNumber = $left !== null
                ? max(1, min($totalFills, $totalFills - $left))
                : $journey->dispenses_completed + 1;
            $totalValue = collect($docRows)->sum(function ($row) {
                return (int) round((float) ($row['sales_value'] ?? 0) * 100);
            });

            if ($latestDate === null || $date->gte($latestDate)) {
                $latestDate = $date;
                $latestLeft = $left;
            }

            // Idempotency: skip a fill we've already recorded (by document number).
            $existingDispense = SparDispenseRecord::where('journey_id', $journey->id)
                ->where('document_number', $documentNumber)
                ->first();
            if ($existingDispense) {
                continue;
            }

            SparDispenseRecord::create([
                'journey_id' => $journey->id,
                'spar_patient_id' => $patient->id,
                'dispense_number' => $fillNumber,
                'status' => 'collected',
                'due_date' => $date,
                'completed_at' => $date,
                'fulfillment_type' => 'collection',
                'document_number' => $documentNumber ?: null,
                'sales_value' => $totalValue,
                'items' => $medications,
            ]);

            $journey->increment('dispenses_completed');
            $journey->refresh();

            if ($journey->isFinalDispense()) {
                $journey->update(['status' => 'renewal_due']);
            } else {
                $journey->update(['next_dispense_date' => $date->copy()->addMonth()]);
            }
        }

        // SPAR told us exactly how many repeats are left after the latest fill:
        // trust that over our own fill count (a file only holds recent fills, so
        // counting them undercounts a script that started months ago).
        if ($latestDate !== null && $latestLeft !== null) {
            $journey->refresh();
            $journey->update([
                'dispenses_completed' => max((int) $journey->dispenses_completed, max(1, $totalFills - $latestLeft)),
                'status' => $latestLeft === 0 ? ($origRepeats === 0 ? 'expired' : 'renewal_due') : 'active',
                'next_dispense_date' => $latestDate->copy()->addMonth(),
                // The first collection that has no repeat behind it needs a new script.
                'renewal_due_date' => $latestDate->copy()->addMonths($latestLeft + 1),
            ]);
        }

        // Close-the-loop reconciliation (FR-B5): now that this import's real
        // dispense facts are recorded on the journey, reconcile the in-app
        // actionable state against them per the configured D2 conflict rule.
        $journey->refresh();
        (new SparReconciler())->reconcileJourney($journey);
    }

    /**
     * Repeat figures for a set of sales lines: [original repeats, repeats left].
     * Repeats are tracked per item, and a script can mix a chronic item (repeats)
     * with a one-off item (none). Items with no repeats are ignored when at least
     * one item HAS repeats; if none do, the script is a one-off (0 and 0).
     * "Left" is the lowest across the repeating items: the first to run out is
     * the one that needs a new script. Both are null when the columns are blank.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function repeatInfo(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $o = trim((string) ($r['repeats'] ?? ''));
            $l = trim((string) ($r['repeat_number'] ?? ''));
            if ($o === '' || !is_numeric($o)) {
                continue;
            }
            $items[] = [(int) $o, ($l !== '' && is_numeric($l)) ? (int) $l : null];
        }

        if (empty($items)) {
            return [null, null];
        }

        $repeating = array_values(array_filter($items, fn ($i) => $i[0] > 0));
        if (empty($repeating)) {
            return [0, 0]; // every item is a one-off
        }

        $orig = max(array_column($repeating, 0));
        $lefts = array_filter(array_column($repeating, 1), fn ($v) => $v !== null);

        return [$orig, empty($lefts) ? null : min($lefts)];
    }

    /**
     * One medicine line as kept on journeys and dispense records.
     */
    private function medicationLine(array $row): array
    {
        $repeats = trim((string) ($row['repeats'] ?? ''));
        $left = trim((string) ($row['repeat_number'] ?? ''));

        return [
            'name' => trim($row['item_description'] ?? ''),
            'nappi_code' => trim($row['nappi_code'] ?? ''),
            'quantity' => (float) ($row['sales_quantity'] ?? 0),
            'value' => (int) round((float) ($row['sales_value'] ?? 0) * 100),
            'schedule' => $row['schedule'] ?? null,
            'supplier' => $row['supplier'] ?? null,
            'brand_name' => $row['brand_name'] ?? null,
            'repeats' => is_numeric($repeats) ? (int) $repeats : null,
            'repeats_left' => is_numeric($left) ? (int) $left : null,
            'paid_by' => trim((string) ($row['transaction_type'] ?? '')) ?: null,
        ];
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

        $this->incompleteLines = 0;

        if ($this->isSpreadsheet($filePath)) {
            [$headers, $valueRows] = $this->readSpreadsheetGrid($filePath);
        } else {
            [$headers, $valueRows] = $this->readDelimitedGrid($filePath);
        }

        // Validate we have expected columns
        if (count($headers) < 5) {
            throw new \RuntimeException('Invalid file format: too few columns detected.');
        }

        $rows = [];
        foreach ($valueRows as $values) {
            // Map to our internal field names
            $mapped = [];
            foreach ($headers as $i => $header) {
                $internalField = self::COLUMN_MAP[$header] ?? Str::snake($header);
                // SECURITY: Strip any null bytes and control characters
                $value = trim((string) ($values[$i] ?? ''));
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
                $mapped[$internalField] = $value;
            }

            // Lines cut off in the export (no quantity AND no profile code)
            // carry no usable data: ignore them and report the count once.
            if (($mapped['sales_quantity'] ?? '') === '' && ($mapped['profile_code'] ?? '') === ''
                && array_key_exists('sales_quantity', $mapped)) {
                $this->incompleteLines++;
                continue;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * True when the file is an Excel workbook (by extension, else by zip magic bytes).
     */
    private function isSpreadsheet(string $filePath): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return true;
        }
        if (in_array($ext, ['csv', 'txt'], true)) {
            return false;
        }

        $fh = @fopen($filePath, 'rb');
        $magic = $fh ? fread($fh, 2) : '';
        if ($fh) {
            fclose($fh);
        }

        return $magic === 'PK';
    }

    /**
     * Read the first sheet of an Excel sales extract into [headers, rows].
     * Dates (stored as Excel serials) become Y-m-d; whole numbers lose the
     * ".0" so barcodes / codes match the text export.
     *
     * @return array{0: array<int,string>, 1: array<int, array<int, string>>}
     */
    private function readSpreadsheetGrid(string $filePath): array
    {
        // Read in row chunks: loading a whole 6,000+ row x 39 column sheet at once
        // exhausts PHP's default 128MB memory limit (PhpSpreadsheet keeps a cell
        // object per cell). Each chunk is loaded, converted, then freed.
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $filter = new class implements IReadFilter {
            public int $start = 1;
            public int $end = 1;

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->start && $row <= $this->end;
            }
        };
        $reader->setReadFilter($filter);

        $chunkSize = 500;
        $headers = [];
        $dateCols = [];
        $rows = [];

        for ($start = 1; ; $start += $chunkSize) {
            $filter->start = $start;
            $filter->end = $start + $chunkSize - 1;

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $lastCol = $sheet->getHighestColumn();
            $highest = $sheet->getHighestRow();

            if ($start > 1 && $highest < $start) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                break; // no cells in this chunk: end of data
            }

            $grid = $sheet->rangeToArray("A{$start}:{$lastCol}{$highest}", null, true, false, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $sheet);

            if ($start === 1) {
                $headers = array_map(fn ($c) => trim((string) $c), (array) array_shift($grid));
                $dateCols = array_keys(array_filter($headers, fn ($h) => $h === 'Date'));
            }

            foreach ($grid as $raw) {
                $values = [];
                foreach ($raw as $i => $cell) {
                    if (in_array($i, $dateCols, true) && is_numeric($cell)) {
                        $values[$i] = ExcelDate::excelToDateTimeObject((float) $cell)->format('Y-m-d');
                    } elseif (is_float($cell) && floor($cell) === $cell && abs($cell) < 1e15) {
                        $values[$i] = number_format($cell, 0, '', '');
                    } else {
                        $values[$i] = $cell === null ? '' : trim((string) $cell);
                    }
                }
                if (implode('', $values) === '') {
                    continue; // fully blank row
                }
                $rows[] = $values;
            }
        }

        return [$headers, $rows];
    }

    /**
     * Read a pipe/comma delimited sales extract into [headers, rows].
     *
     * @return array{0: array<int,string>, 1: array<int, array<int, string>>}
     */
    private function readDelimitedGrid(string $filePath): array
    {
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

        // SPAR quirk: every line (header + data) carries a trailing comma, so the
        // final field arrives glued to it — the header becomes "Script Number,"
        // and values become "9900100 ,". That broke COLUMN_MAP lookups for the
        // last column (Script Number), silently falling back to document_number
        // for journey grouping. Strip a trailing comma (+ surrounding space) from
        // the final header so it maps correctly. The matching value fix is below.
        if (!empty($headers)) {
            $lastIdx = count($headers) - 1;
            $headers[$lastIdx] = trim(rtrim(trim($headers[$lastIdx]), ','));
        }

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $values = str_getcsv($line, $delimiter);

            // SPAR quirk (mirror of the header fix): the final value on each line
            // carries the row's trailing comma, e.g. "9900100 ,". Strip it so the
            // last column (Script Number) parses to its real value.
            if (!empty($values)) {
                $lastVal = count($values) - 1;
                $values[$lastVal] = rtrim(trim((string) $values[$lastVal]), ',');
            }

            $rows[] = $values;
        }

        return [$headers, $rows];
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
