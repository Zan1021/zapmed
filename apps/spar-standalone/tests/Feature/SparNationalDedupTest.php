<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparImportService;

/**
 * National identity, Phase 2 — a patient who fills at two different SPAR stores
 * is ONE patient with dispense history from both (no duplicate), matched on the
 * profile-code blind index independent of pharmacy.
 */
class SparNationalDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spar.onboarding_mode' => 'pharmacist_capture']);
    }

    public function test_same_profile_at_two_stores_is_one_patient(): void
    {
        $csvA = $this->writeSales('SPAR Plett', '9900001', '9900100');
        $csvB = $this->writeSales('SPAR Knysna', '9900002', '9900200');

        $svc = new SparImportService();
        $svc->importFile($csvA);
        $svc->importFile($csvB);

        // ONE patient, not two — despite two stores.
        $this->assertSame(1, SparPatient::count());

        $patient = SparPatient::first();
        // Journeys from BOTH pharmacies attached to the one patient.
        $pharmacyIds = SparPrescriptionJourney::where('spar_patient_id', $patient->id)
            ->pluck('spar_pharmacy_id')->unique();
        $this->assertCount(2, $pharmacyIds);
    }

    public function test_conflicting_phone_flags_review(): void
    {
        // First import (paired) establishes the patient with a phone from the
        // Drug Usage identity file.
        $csvA = $this->writeSales('SPAR Plett', '9900001', '9900100');
        $drugA = $this->writeDrug('0820000001');
        (new SparImportService())->importPair($csvA, $drugA);
        $patient = SparPatient::first();
        $this->assertSame('0820000001', $patient->cellphone);
        $this->assertFalse($patient->needs_identity_review);

        // Second import: same profile, different store, CONFLICTING phone in the
        // Drug Usage file → flag review, don't silently merge.
        $csvB = $this->writeSales('SPAR Knysna', '9900002', '9900200');
        $drugB = $this->writeDrug('0999999999');
        (new SparImportService())->importPair($csvB, $drugB);

        $this->assertSame(1, SparPatient::count());
        $this->assertTrue($patient->fresh()->needs_identity_review);
    }

    private function writeDrug(string $phone): string
    {
        $path = tempnam(sys_get_temp_dir(), 'drug') . '.xlsx';
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Drug Usage');
        $sheet->setCellValue('A6', 'Drug Usage');
        $header = ['Surname', 'Medaid Cd', 'Sms', 'Sms Status', 'Deliver', 'Mobile', 'Address',
            'Firstname', 'Drug', 'Nappi Cd', 'Script No', 'Rpts', 'Script Date', 'Medical Aid No',
            'Invoice Date', 'Levy Amount', 'Message', 'Member Home Nr', 'Member Mobile Nr',
            'Member Number', 'Profile Code', 'Dependent Code'];
        $sheet->fromArray($header, null, 'A8');
        $sheet->fromArray([
            ['NAIDOO', 'MA', 'C', '', '', $phone, '12 Marine Dr', 'PRIYA', 'CO-COPALIA TAB 28', '3001675001', 9900100, 6, '2026-07-05', 'GEM1', '', 100, '', '', $phone, 83001, '990001', '00'],
        ], null, 'A9');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        return $path;
    }

    private function writeSales(string $store, string $bhf, string $doc, string $phone = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sales') . '.csv';
        $header = 'Store Name|Date|Time|Document Number|Cashier Pharmacist Number|Barcode|Bhf|Stock Code|Nappi|Item Description|Unit Size|Schedule|Sales Quantity|Sales Value Excl|Vat|Discount Percentage|Discount Amount|Cost|GP|Dispensing Fee|Department|Supplier|Brand Name|TransactionType|Medical Aid|Medical Aid Option|Client Name|Profile Code|Dependent Code|Dependent Relation|Repeats|Repeat number|Claimed|Levy|Age|Gender|Doctor|Doctor BHF|Script Number|Cellphone,';
        // Same profile 990001/00 in both files; the trailing Cellphone col is an
        // extra mapped column (COLUMN_MAP maps "Cellphone" → cellphone).
        $row = "{$store}|2026-07-05|12|{$doc}|D1|6005534003958|{$bhf}|6005534003958|3001675001|CO-COPALIA TAB 28|28|3|1|185.00|1.15||0.00|136.90|0.26||MEDICATION S3|Novartis||MedicalAid|GEMSP|GOVERNMENT|990001|990001|00|M|6|2|0||54|F|PATEL A|0561339|{$doc}|{$phone} ,";
        file_put_contents($path, "\xEF\xBB\xBF" . $header . "\r\n" . $row . "\r\n");

        return $path;
    }
}
