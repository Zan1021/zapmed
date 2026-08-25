<?php

namespace App\Services;

use App\Models\SparDispenseRecord;
use App\Models\SparImportBatch;
use App\Models\SparImportLog;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SparImportService
{
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
    ];

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

            // Group rows by profile_code + store to process per-patient
            $grouped = collect($rows)->groupBy(function ($row) {
                return ($row['profile_code'] ?? '') . '|' . ($row['store_name'] ?? '');
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

            // Find or create SPAR patient
            $patient = SparPatient::firstOrCreate(
                [
                    'spar_pharmacy_id' => $pharmacy->id,
                    'profile_code' => $profileCode,
                    'dependent_code' => trim($firstRow['dependent_code'] ?? '0'),
                ],
                [
                    'dependent_relation' => trim($firstRow['dependent_relation'] ?? null),
                    'medical_aid_name' => trim($firstRow['medical_aid'] ?? null),
                    'medical_aid_option' => trim($firstRow['medical_aid_option'] ?? null),
                    'is_primary_member' => ($firstRow['dependent_code'] ?? '0') == '0',
                    'metadata' => [
                        'age' => $firstRow['age'] ?? null,
                        'gender' => $firstRow['gender'] ?? null,
                        'client_name' => $firstRow['client_name'] ?? null,
                    ],
                ]
            );

            $wasCreated = $patient->wasRecentlyCreated;

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
     * Process a group of rows for the same script/prescription.
     */
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
