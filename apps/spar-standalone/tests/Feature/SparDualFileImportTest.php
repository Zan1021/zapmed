<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparImportService;

/**
 * End-to-end: import the SPAR sales extract + Drug Usage report together and
 * confirm patients get their dispense history (from the CSV) AND their identity
 * (from the xlsx), linked on (profile_code, dependent_code).
 *
 * Mirrors the demo files in Documents/Zapmed/Spar/demo. Fixtures are generated
 * in-test so the suite is self-contained.
 */
class SparDualFileImportTest extends TestCase
{
    use RefreshDatabase;

    private string $salesPath;
    private string $drugPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spar.onboarding_mode' => 'pharmacist_capture']); // identity should come from the xlsx, not the CSV
        $this->salesPath = tempnam(sys_get_temp_dir(), 'sales') . '.csv';
        $this->drugPath = tempnam(sys_get_temp_dir(), 'drug') . '.xlsx';
        $this->writeSalesCsv($this->salesPath);
        $this->writeDrugUsageXlsx($this->drugPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->salesPath, $this->drugPath] as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    public function test_paired_import_links_identity_to_dispense_history(): void
    {
        $batch = (new SparImportService())->importPair($this->salesPath, $this->drugPath);

        $this->assertSame('completed', $batch->status);

        // Three people: principal 990001/00, dependent 990001/01, single 990002/00.
        $this->assertSame(3, SparPatient::count());

        // Principal identity came from the Drug Usage file.
        $priya = $this->patient('990001', '00');
        $this->assertNotNull($priya);
        $this->assertSame('PRIYA', $priya->first_name);
        $this->assertSame('NAIDOO', $priya->last_name);
        $this->assertSame('0821110001', $priya->cellphone);
        $this->assertTrue($priya->is_primary_member);
        $this->assertSame('12 Marine Drive', $priya->metadata['address'] ?? null);

        // Dependent identity also matched on its own dependent code.
        $raj = $this->patient('990001', '01');
        $this->assertSame('RAJ', $raj->first_name);
        $this->assertSame('0821110002', $raj->cellphone);
        $this->assertFalse($raj->is_primary_member);

        // Dispense history came from the sales CSV.
        $this->assertGreaterThanOrEqual(1, SparPrescriptionJourney::where('spar_patient_id', $priya->id)->count());
        $this->assertGreaterThanOrEqual(1, SparDispenseRecord::where('spar_patient_id', $priya->id)->count());

        // Single member with medical-aid transaction.
        $johan = $this->patient('990002', '00');
        $this->assertSame('JOHAN', $johan->first_name);
        $this->assertSame('VAN WYK', $johan->last_name);

        // Security: the identity file was deleted after processing.
        $this->assertFileDoesNotExist($this->drugPath);
    }

    public function test_missing_link_columns_is_rejected(): void
    {
        // Write a drug-usage file with NO Profile/Dependent columns.
        $bad = tempnam(sys_get_temp_dir(), 'bad') . '.xlsx';
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray(['Surname', 'Firstname', 'Mobile'], null, 'A1');
        $sheet->fromArray(['NAIDOO', 'PRIYA', '0821110001'], null, 'A2');
        (new Xlsx($ss))->save($bad);

        $this->expectException(\RuntimeException::class);
        try {
            (new SparImportService())->importPair($this->salesPath, $bad);
        } finally {
            @unlink($bad);
        }
    }

    private function patient(string $profile, string $dependent): ?SparPatient
    {
        return SparPatient::get()->first(function (SparPatient $p) use ($profile, $dependent) {
            return $p->profile_code === $profile
                && str_pad((string) $p->dependent_code, 2, '0', STR_PAD_LEFT) === $dependent;
        });
    }

    // --- fixtures -----------------------------------------------------------

    private function writeSalesCsv(string $path): void
    {
        $header = 'Store Name|Date|Time|Document Number|Cashier Pharmacist Number|Barcode|Bhf|Stock Code|Nappi|Item Description|Unit Size|Schedule|Sales Quantity|Sales Value Excl|Vat|Discount Percentage|Discount Amount|Cost|GP|Dispensing Fee|Department|Supplier|Brand Name|TransactionType|Medical Aid|Medical Aid Option|Client Name|Profile Code|Dependent Code|Dependent Relation|Repeats|Repeat number|Claimed|Levy|Age|Gender|Doctor|Doctor BHF|Script Number,';

        $rows = [
            // Priya 990001/00
            "SPAR Demo|2026-07-05|12|9900100|D1|6005534003958|9900001|6005534003958|3001675001|CO-COPALIA TAB 28|28|3|1|185.00|1.15||0.00|136.90|0.26||MEDICATION S3|Novartis||MedicalAid|GEMSP|GOVERNMENT|990001|990001|00|M|6|2|0||54|F|PATEL A|0561339|9900100 ,",
            // Raj 990001/01
            "SPAR Demo|2026-07-08|14|9900110|D1|6001137101886|9900001|6001137101886|708121001|ASPAVOR 10MG TAB 30|30|4|1|169.00|1.15||0.00|125.06|0.26||DISPENSARY|Viatris||MedicalAid|GEMSP|GOVERNMENT|990001|990001|01|D         |5|3|0||57|M|PATEL A|0561339|9900110 ,",
            // Johan 990002/00 (cash)
            "SPAR Demo|2026-07-03|12|9900200|D1|6001137101886|9900001|6001137101886|708121001|ASPAVOR 10MG TAB 30|30|4|1|169.00|1.15||0.00|125.06|0.26||DISPENSARY|Viatris||Cash|PR36|PRIVATE|990002|990002|00|M|6|6|0||61|M|NEL H|0250012|9900200 ,",
        ];

        file_put_contents($path, "\xEF\xBB\xBF" . $header . "\r\n" . implode("\r\n", $rows) . "\r\n");
    }

    private function writeDrugUsageXlsx(string $path): void
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Drug Usage');
        // Preamble to prove header auto-location works.
        $sheet->setCellValue('A1', 'DEMO APTEEK / PHARMACY');
        $sheet->setCellValue('A5', 'Report Date: 01 Sept 2026');
        $sheet->setCellValue('A6', 'Drug Usage');

        $header = ['Surname', 'Medaid Cd', 'Sms', 'Sms Status', 'Deliver', 'Mobile', 'Address',
            'Firstname', 'Drug', 'Nappi Cd', 'Script No', 'Rpts', 'Script Date', 'Medical Aid No',
            'Invoice Date', 'Levy Amount', 'Message', 'Member Home Nr', 'Member Mobile Nr',
            'Member Number', 'Profile Code', 'Dependent Code'];
        $sheet->fromArray($header, null, 'A8');

        $data = [
            ['NAIDOO', 'MA', 'C', '', '', '0821110001', '12 Marine Drive', 'PRIYA', 'CO-COPALIA TAB 28', '3001675001', 9900100, 6, '2026-07-05', 'GEM8830001', '', 102.5, '', '', '0821110001', 83001, '990001', '00'],
            ['NAIDOO', 'MA', 'C', '', '', '0821110002', '12 Marine Drive', 'RAJ', 'ASPAVOR 10MG TAB 30', '708121001', 9900110, 5, '2026-07-08', 'GEM8830001', '', 78.5, '', '', '0821110002', 83002, '990001', '01'],
            ['VAN WYK', 'PR', 'C', '', '', '0723330010', '8 Longships Ave', 'JOHAN', 'ASPAVOR 10MG TAB 30', '708121001', 9900200, 6, '2026-07-03', '', '', 78.5, '', '', '0723330010', 83003, '990002', '00'],
        ];
        $sheet->fromArray($data, null, 'A9');

        (new Xlsx($ss))->save($path);
    }
}
