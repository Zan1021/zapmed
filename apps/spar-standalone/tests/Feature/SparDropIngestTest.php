<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparDropIngestor;

/**
 * FTP-drop ingestion: the automated path. Files landing in the drop directory
 * (as an FTP account would deliver them) are paired + imported by the same
 * service the admin upload uses.
 */
class SparDropIngestTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spar.onboarding_mode' => 'pharmacist_capture']);
        config(['spar.import.archive_processed' => false]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spar_drop_' . uniqid();
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_ingests_a_dropped_pair(): void
    {
        $this->writeSales($this->dir . DIRECTORY_SEPARATOR . 'Demo SalesExtract072026.csv');
        $this->writeDrug($this->dir . DIRECTORY_SEPARATOR . 'Demo Drug Usage 01 Sept 2026.xlsx');

        $result = (new SparDropIngestor())->ingestDirectory($this->dir);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, SparPatient::count());
        $priya = SparPatient::get()->first(fn ($p) => $p->profile_code === '990001');
        $this->assertSame('PRIYA', $priya->first_name);

        // Source files deleted after processing (PHI).
        $this->assertCount(0, glob($this->dir . DIRECTORY_SEPARATOR . '*.csv'));
        $this->assertCount(0, glob($this->dir . DIRECTORY_SEPARATOR . '*.xlsx'));
    }

    public function test_skips_when_no_sales_file(): void
    {
        $this->writeDrug($this->dir . DIRECTORY_SEPARATOR . 'Demo Drug Usage 01 Sept 2026.xlsx');

        $result = (new SparDropIngestor())->ingestDirectory($this->dir);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, SparPatient::count());
    }

    private function writeSales(string $path): void
    {
        $header = 'Store Name|Date|Time|Document Number|Cashier Pharmacist Number|Barcode|Bhf|Stock Code|Nappi|Item Description|Unit Size|Schedule|Sales Quantity|Sales Value Excl|Vat|Discount Percentage|Discount Amount|Cost|GP|Dispensing Fee|Department|Supplier|Brand Name|TransactionType|Medical Aid|Medical Aid Option|Client Name|Profile Code|Dependent Code|Dependent Relation|Repeats|Repeat number|Claimed|Levy|Age|Gender|Doctor|Doctor BHF|Script Number,';
        $rows = [
            "SPAR Demo|2026-07-05|12|9900100|D1|6005534003958|9900001|6005534003958|3001675001|CO-COPALIA TAB 28|28|3|1|185.00|1.15||0.00|136.90|0.26||MEDICATION S3|Novartis||MedicalAid|GEMSP|GOVERNMENT|990001|990001|00|M|6|2|0||54|F|PATEL A|0561339|9900100 ,",
            "SPAR Demo|2026-07-03|12|9900200|D1|6001137101886|9900001|6001137101886|708121001|ASPAVOR 10MG TAB 30|30|4|1|169.00|1.15||0.00|125.06|0.26||DISPENSARY|Viatris||Cash|PR36|PRIVATE|990002|990002|00|M|6|6|0||61|M|NEL H|0250012|9900200 ,",
        ];
        file_put_contents($path, "\xEF\xBB\xBF" . $header . "\r\n" . implode("\r\n", $rows) . "\r\n");
    }

    private function writeDrug(string $path): void
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Drug Usage');
        $sheet->setCellValue('A6', 'Drug Usage');
        $header = ['Surname', 'Medaid Cd', 'Sms', 'Sms Status', 'Deliver', 'Mobile', 'Address',
            'Firstname', 'Drug', 'Nappi Cd', 'Script No', 'Rpts', 'Script Date', 'Medical Aid No',
            'Invoice Date', 'Levy Amount', 'Message', 'Member Home Nr', 'Member Mobile Nr',
            'Member Number', 'Profile Code', 'Dependent Code'];
        $sheet->fromArray($header, null, 'A8');
        $data = [
            ['NAIDOO', 'MA', 'C', '', '', '0821110001', '12 Marine Drive', 'PRIYA', 'CO-COPALIA TAB 28', '3001675001', 9900100, 6, '2026-07-05', 'GEM8830001', '', 102.5, '', '', '0821110001', 83001, '990001', '00'],
            ['VAN WYK', 'PR', 'C', '', '', '0723330010', '8 Longships Ave', 'JOHAN', 'ASPAVOR 10MG TAB 30', '708121001', 9900200, 6, '2026-07-03', '', '', 78.5, '', '', '0723330010', 83003, '990002', '00'],
        ];
        $sheet->fromArray($data, null, 'A9');
        (new Xlsx($ss))->save($path);
    }
}
