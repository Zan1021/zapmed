<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparImportLog;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparImportService;

/**
 * The Wapadrand (Oct 2026) layout: an Excel sales sheet + a pipe-delimited
 * member extract (LastName / ProfileNumber / DependantNumber / Consent ...).
 * Fixtures reproduce the real quirks: padded values, "NULL" text, broken
 * trailing lines, cut-off sales lines, Excel date serials, "0 repeats".
 */
class SparNewLayoutImportTest extends TestCase
{
    use RefreshDatabase;

    private string $salesPath;
    private string $memberPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spar.onboarding_mode' => 'pharmacist_capture']);
        $this->salesPath = tempnam(sys_get_temp_dir(), 'sales') . '.xlsx';
        $this->memberPath = tempnam(sys_get_temp_dir(), 'member') . '.csv';
        $this->writeSalesXlsx($this->salesPath);
        $this->writeMemberCsv($this->memberPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->salesPath, $this->memberPath] as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    public function test_excel_sales_and_pipe_member_file_import_and_link(): void
    {
        $batch = (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        $this->assertSame('completed', $batch->status);

        $anna = $this->patient('880101', '00');
        $this->assertNotNull($anna);
        $this->assertSame('ANNA', $anna->first_name);
        $this->assertSame('TESTER', $anna->last_name);
        $this->assertSame('0820000101', $anna->cellphone);
        // SPAR's consent flag is recorded but never opts the patient in.
        $this->assertSame('Y', $anna->metadata['source_consent'] ?? null);
        $this->assertNotSame('opted_in', $anna->consent_status);

        $bella = $this->patient('880102', '01');
        $this->assertNotNull($bella);
        $this->assertSame('N', $bella->metadata['source_consent'] ?? null);

        // Dispense date came through the Excel serial conversion.
        $journey = SparPrescriptionJourney::where('spar_patient_id', $anna->id)->first();
        $this->assertNotNull($journey);
        $this->assertSame('2026-10-16', $journey->start_date->format('Y-m-d'));

        // Member file is deleted after import (PHI).
        $this->assertFileDoesNotExist($this->memberPath);
    }

    public function test_zero_repeats_is_a_single_fill_script(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        $oneOff = SparPrescriptionJourney::where('script_number', '70001')->first();
        $this->assertNotNull($oneOff);
        $this->assertSame(1, (int) $oneOff->total_dispenses);

        // 5 repeats on the script = 6 fills; 4 repeats left = 2 fills done, still active.
        $repeating = SparPrescriptionJourney::where('script_number', '70002')->first();
        $this->assertSame(6, (int) $repeating->total_dispenses);
        $this->assertSame(2, (int) $repeating->dispenses_completed);
        $this->assertSame('active', $repeating->status);
        $this->assertSame('2027-03-06', $repeating->renewal_due_date->format('Y-m-d')); // fill + (4 left + 1) months
    }

    public function test_no_repeats_left_means_renewal_due(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        // 6 repeats, 0 left: the last fill has been collected.
        $last = SparPrescriptionJourney::where('script_number', '70003')->first();
        $this->assertSame(7, (int) $last->total_dispenses);
        $this->assertSame(7, (int) $last->dispenses_completed);
        $this->assertSame('renewal_due', $last->status);

        // A one-off script (no repeats) is simply finished: it is not chronic
        // medication, so it must not nag the patient or staff for a renewal.
        $oneOff = SparPrescriptionJourney::where('script_number', '70001')->first();
        $this->assertSame('expired', $oneOff->status);
    }

    public function test_household_without_a_main_member_is_flagged_for_review(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        // 880102/01 is a dependant and 880102/00 is not in the files: staff must confirm the contact.
        $dependant = $this->patient('880102', '01');
        $this->assertTrue($dependant->needs_identity_review);
        $this->assertStringContainsString('No main member', (string) $dependant->identity_review_reason);

        // Main members are never flagged.
        $this->assertFalse($this->patient('880101', '00')->needs_identity_review);
    }

    public function test_date_of_birth_is_stored_and_gives_age(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        $anna = $this->patient('880101', '00');
        $this->assertSame('1949-07-01', $anna->date_of_birth);
        $this->assertGreaterThan(70, $anna->ageYears());
    }

    public function test_reimporting_the_same_files_creates_no_duplicates(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);
        $journeys = SparPrescriptionJourney::count();
        $dispenses = \Zapmed\SparCore\Models\SparDispenseRecord::count();

        // The import deletes its source files, so write fresh copies and run it again.
        $this->writeSalesXlsx($this->salesPath);
        $this->writeMemberCsv($this->memberPath);
        $batch = (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        $this->assertSame('completed', $batch->status);
        $this->assertSame(3, SparPatient::count());
        $this->assertSame($journeys, SparPrescriptionJourney::count());
        $this->assertSame($dispenses, \Zapmed\SparCore\Models\SparDispenseRecord::count());
    }

    public function test_renewal_due_script_stays_open_for_follow_up(): void
    {
        (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        // Last repeat collected: the renewal loop is OPEN so staff can nudge/snooze it.
        $due = SparPrescriptionJourney::where('script_number', '70003')->first();
        $this->assertSame('renewal_due', $due->status);
        $this->assertTrue($due->actionStatus()->isOpen());

        // A finished one-off script needs no follow-up: closed.
        $oneOff = SparPrescriptionJourney::where('script_number', '70001')->first();
        $this->assertTrue($oneOff->actionStatus()->isResolved());
    }

    public function test_cut_off_sales_lines_are_ignored_and_reported(): void
    {
        $batch = (new SparImportService())->importPair($this->salesPath, $this->memberPath);

        // 3 members only: the cut-off line must not create anyone.
        $this->assertSame(3, SparPatient::count());
        $this->assertSame(0, (int) $batch->records_failed);

        $log = SparImportLog::where('batch_id', $batch->id)
            ->where('error_message', 'like', '%incomplete sales line%')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('1 incomplete', $log->error_message);
    }

    private function patient(string $profile, string $dependent): ?SparPatient
    {
        return SparPatient::get()->first(function (SparPatient $p) use ($profile, $dependent) {
            return $p->profile_code === $profile
                && str_pad((string) $p->dependent_code, 2, '0', STR_PAD_LEFT) === $dependent;
        });
    }

    // --- fixtures -----------------------------------------------------------

    private function writeSalesXlsx(string $path): void
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $header = ['Store Name', 'Date', 'Time', 'Document Number', 'Cashier Pharmacist Number', 'Barcode', 'Bhf',
            'Stock Code', 'Nappi', 'Item Description', 'Unit Size', 'Schedule', 'Sales Quantity', 'Sales Value Excl',
            'Vat', 'Discount Percentage', 'Discount Amount', 'Cost', 'GP', 'Dispensing Fee', 'Department', 'Supplier',
            'Brand Name', 'TransactionType', 'Medical Aid', 'Medical Aid Option', 'Client Name', 'Profile Code',
            'Dependent Code', 'Dependent Relation', 'Repeats', 'Repeat number', 'Claimed', 'Levy', 'Age', 'Gender',
            'Doctor', 'Doctor BHF', 'Script Number'];
        $sheet->fromArray($header, null, 'A1');

        $serial = static fn (string $d) => ExcelDate::PHPToExcel(new \DateTime($d));
        $store = 'Pharmacy at SPAR-Wapadrand';

        $rows = [
            // Anna 880101/0: one-off script (Repeats 0)
            [$store, $serial('2026-10-16'), 11, 70011, 'KP', 6006498008706, 1061046, 6006498008706, 738786004,
                'ALLOPURINOL 300 CIPLA 300MG TAB 30', 30, 3, 1, 98.8, 1.15, null, 0, 76.05, 0.23, null, 'SCHEDULE 1 - 5',
                null, 'Cipla', 'MedicalAid', 'DISCD26A', 'DISCOVERY', 880101, 880101, 0, 'D         ', 0, 0, 1, null, 64, 'F',
                'DR TEST A', 1000001, 70001],
            // Bella 880102/1: repeating script (Repeats 5)
            [$store, $serial('2026-10-06'), 15, 70012, 'EV', 6009657980387, 1061046, 6009657980387, 703916001,
                'AMLOC 5MG TAB 30', 30, 3, 1, 61.75, 1.15, null, 0, 45.13, 0.27, null, 'SCHEDULE 1 - 5',
                null, 'Pharma Dynamics', 'MedicalAid', 'DISCSMTC', 'DISCOVERY', 880102, 880102, 1, 'D         ', 5, 4, 0, null, 73, 'F',
                'DR TEST B', 1000002, 70002],
            // Johan 990002/0
            [$store, $serial('2026-10-20'), 9, 70013, 'KP', 6001137101886, 1061046, 6001137101886, 708121001,
                'ASPAVOR 10MG TAB 30', 30, 4, 1, 169.0, 1.15, null, 0, 125.06, 0.26, null, 'DISPENSARY',
                null, 'Viatris', 'Cash', 'PR36', 'PRIVATE', 990002, 990002, 0, 'D         ', 6, 0, 0, null, 61, 'M',
                'DR TEST C', 1000003, 70003],
            // Cut-off line: only the first 10 columns exist.
            [$store, $serial('2026-10-16'), 11, 70011, 'KP', 6009657980783, 1061046, 6009657980783, 891287008,
                'ENAP CO 20MG/12.5MG 20MG;12'],
        ];
        $sheet->fromArray($rows, null, 'A2', true); // strict: keep real 0 values
        (new Xlsx($ss))->save($path);
    }

    private function writeMemberCsv(string $path): void
    {
        $header = 'ProfileNumber|Rams|DependantNumber|RSAID|LastName|Intials|FirstName|DateOfBirth|HomePhone|WorkTel|Cellphone|Email|Orginal Number of repeats||MedicalAidName|MedicalaidOption|MedicalAidnumber|medicalAidIsDependant|Consent|HHMMSS|OrigninalRxNumber|RXNumber|PrescribingDoctor|PrescribingDoctorName|PrescribingDoctorEmail|Nappi9|RepeatsLeft|DispenseDate|Qty';
        $lines = [
            '880101|1061046|0         |4907015000088|TESTER|AT|ANNA|01 Jul 1949|||0820000101||0||TEST MEDICAL SCHEME|NULL|900000001|N|Y|120000||70001   |0000001|NULL||738786004|0|27 Oct 2026|1',
            '880102|1061046|1         |960101|SAMPLE|BS|BELLA|01 Jan 1996|||0820000102||5||PRIVATE|NA||N|N|130000||70002   |0000002|NULL||703916001|2|25 Oct 2026|60',
            '990002|1061046|0         |7001015009087|VAN WYK|J|JOHAN|01 Jan 1970|||0723330010||6||PRIVATE|NA||N|Y|090000||70003   |0000003|NULL||708121001|2|20 Oct 2026|30',
            '',
            '3',
            '',
        ];
        file_put_contents($path, "\xEF\xBB\xBF" . $header . "\r\n" . implode("\r\n", $lines) . "\r\n");
    }
}
