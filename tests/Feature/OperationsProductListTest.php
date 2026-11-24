<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\ProductListImport;
use App\Models\CrmAuditEvent;
use App\Models\Medication;
use App\Models\User;
use App\Services\Operations\ProductCatalogIO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4.5 product list import and export (Craig's guide 6.4): a CSV round trip for the catalogue. The file is checked first,
 * a bad row stops everything, a blank cell never wipes a product, and the export cannot run a formula.
 */
class OperationsProductListTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prod-io-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function io(): ProductCatalogIO
    {
        return app(ProductCatalogIO::class);
    }

    private function csv(array $rows, string $delimiter = ','): string
    {
        $path = $this->dir.DIRECTORY_SEPARATOR.uniqid().'.csv';
        $h = fopen($path, 'w');
        foreach ($rows as $r) {
            fputcsv($h, $r, $delimiter);
        }
        fclose($h);

        return $path;
    }

    private function med(array $a = []): Medication
    {
        return Medication::create($a + ['name' => 'Alpha', 'form' => 'tablet', 'strength' => '5mg', 'nappi_code' => 'N100', 'is_active' => true]);
    }

    private const HEAD = ['nappi_code', 'name', 'form', 'strength', 'price_rand', 'repeat_cycle_days', 'is_subscription', 'is_active', 'category'];

    public function test_a_new_product_is_added_and_the_price_is_turned_into_cents(): void
    {
        $r = $this->io()->import($this->ops, $this->csv([self::HEAD, ['N200', 'Beta', 'injection', '2.5mg', '1 210,50', '28', 'yes', 'yes', 'weight-loss']]));

        $this->assertSame(1, $r['created']);
        $m = Medication::where('nappi_code', 'N200')->firstOrFail();
        $this->assertSame(121050, $m->price_cents);
        $this->assertSame(28, $m->repeat_cycle_days);
        $this->assertTrue($m->is_subscription);
        $this->assertSame('weight-loss', $m->category);
        $this->assertTrue($m->is_active);
    }

    public function test_a_new_product_without_the_yes_no_columns_uses_the_defaults(): void
    {
        $this->io()->import($this->ops, $this->csv([['name', 'form', 'strength'], ['Gamma', 'tablet', '10mg']]));

        $m = Medication::where('name', 'Gamma')->firstOrFail();
        $this->assertTrue($m->is_active, 'On by default.');
        $this->assertFalse($m->is_subscription);
    }

    public function test_an_existing_product_is_matched_by_nappi_and_only_changed_cells_are_written(): void
    {
        $m = $this->med(['price_cents' => 10000, 'category' => 'old', 'generic_name' => 'keep me']);

        $r = $this->io()->import($this->ops, $this->csv([self::HEAD, ['N100', 'Alpha', 'tablet', '5mg', '150.00', '', '', '', '']]));

        $this->assertSame([0, 1, 0], [$r['created'], $r['updated'], $r['unchanged']]);
        $m = $m->fresh();
        $this->assertSame(15000, $m->price_cents);
        $this->assertSame('old', $m->category, 'A blank cell leaves it alone.');
        $this->assertSame('keep me', $m->generic_name, 'A column that is not in the file is never touched.');
        $this->assertTrue($m->is_active, 'Blank is_active changes nothing.');
        $this->assertSame(1, Medication::count());
    }

    public function test_without_a_nappi_code_the_match_is_name_strength_and_form(): void
    {
        $this->med(['nappi_code' => null, 'price_cents' => 100]);

        $r = $this->io()->import($this->ops, $this->csv([['name', 'form', 'strength', 'price_rand'], ['  ALPHA ', 'Tablet', '5MG', '3.00']]));

        $this->assertSame([0, 1], [$r['created'], $r['updated']]);
        $this->assertSame(300, Medication::first()->price_cents);

        $r = $this->io()->import($this->ops, $this->csv([['name', 'form', 'strength'], ['Alpha', 'tablet', '10mg']]));
        $this->assertSame(1, $r['created'], 'A different strength is a different product.');
    }

    public function test_the_yes_no_columns_can_switch_a_product_off_and_a_row_with_no_change_is_unchanged(): void
    {
        $this->med();

        $r = $this->io()->import($this->ops, $this->csv([self::HEAD, ['N100', 'Alpha', 'tablet', '5mg', '', '', 'no', 'no', '']]));
        $this->assertFalse(Medication::first()->is_active);

        $again = $this->io()->import($this->ops, $this->csv([self::HEAD, ['N100', 'Alpha', 'tablet', '5mg', '', '', 'no', 'no', '']]));
        $this->assertSame([0, 0, 1], [$again['created'], $again['updated'], $again['unchanged']]);
    }

    public function test_preview_writes_nothing(): void
    {
        $this->med();
        $before = Medication::count();

        $r = $this->io()->preview($this->ops, $this->csv([self::HEAD, ['N999', 'New', 'tablet', '1mg', '5', '', '', '', ''], ['N100', 'Alpha', 'tablet', '5mg', '99', '', '', '', '']]));

        $this->assertSame([1, 1], [$r['created'], $r['updated']]);
        $this->assertSame($before, Medication::count());
        $this->assertSame(0, Medication::where('price_cents', 9900)->count());
        $this->assertSame(0, CrmAuditEvent::where('action', 'products_imported')->count());
    }

    public function test_one_bad_row_stops_the_whole_file_and_every_problem_is_listed(): void
    {
        $this->med(['price_cents' => 100]);
        $path = $this->csv([
            self::HEAD,
            ['N100', 'Alpha', 'tablet', '5mg', '999', '', '', '', ''],           // fine
            ['N201', '', 'tablet', '5mg', '5', '', '', '', ''],                  // no name
            ['N202', 'Delta', 'tablet', '5mg', 'cheap', '', '', '', ''],         // bad price
            ['N203', 'Epsilon', 'tablet', '5mg', '5', '400', '', '', ''],        // bad days
            ['N204', 'Zeta', 'tablet', '5mg', '5', '', 'maybe', '', ''],         // bad yes/no
            ['N205', str_repeat('x', 300), 'tablet', '5mg', '', '', '', '', ''], // too long
        ]);

        $r = $this->io()->import($this->ops, $path);

        $this->assertSame(5, count($r['errors']));
        $this->assertSame([3, 4, 5, 6, 7], array_column($r['errors'], 'row'));
        $this->assertSame(100, Medication::first()->price_cents, 'The good row was NOT applied either.');
        $this->assertSame(1, Medication::count());
        $this->assertSame(0, CrmAuditEvent::where('action', 'products_imported')->count());
    }

    public function test_the_same_product_twice_in_a_file_is_refused(): void
    {
        $r = $this->io()->preview($this->ops, $this->csv([self::HEAD, ['N300', 'Dup', 'tablet', '1mg', '', '', '', '', ''], ['N300', 'Dup again', 'tablet', '1mg', '', '', '', '', '']]));

        $this->assertCount(1, $r['errors']);
        $this->assertStringContainsString('twice', $r['errors'][0]['message']);
        $this->assertSame(3, $r['errors'][0]['row']);
    }

    public function test_the_file_needs_the_basic_columns_and_a_sensible_size(): void
    {
        foreach ([[['name', 'form'], ['A', 'tablet']], [[], []]] as $rows) {
            try {
                $this->io()->preview($this->ops, $this->csv($rows));
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }

        $this->expectException(ValidationException::class);
        $this->io()->preview($this->ops, $this->dir.DIRECTORY_SEPARATOR.'missing.csv');
    }

    public function test_excel_style_files_work_semicolons_a_bom_blank_lines_and_unknown_columns(): void
    {
        $path = $this->dir.DIRECTORY_SEPARATOR.'excel.csv';
        file_put_contents($path, "\xEF\xBB\xBFName;Form;Strength;Price_Rand;Colour\nOmega;tablet;5mg;12,50;red\n\n;;;;\n");

        $r = $this->io()->import($this->ops, $path);

        $this->assertSame(1, $r['created']);
        $this->assertSame(['colour'], $r['ignored_columns']);
        $this->assertSame(1250, Medication::where('name', 'Omega')->value('price_cents'));
    }

    public function test_an_import_is_audited_once_with_the_counts(): void
    {
        $this->med();
        $this->io()->import($this->ops, $this->csv([self::HEAD, ['N100', 'Alpha', 'tablet', '5mg', '77', '', '', '', ''], ['N400', 'Fresh', 'tablet', '1mg', '', '', '', '', '']]));

        $e = CrmAuditEvent::where('action', 'products_imported')->firstOrFail();
        $this->assertSame(['created' => 1, 'updated' => 1, 'unchanged' => 0], $e->after);
        $this->assertSame($this->ops->id, $e->actor_id);
    }

    public function test_export_has_every_column_prices_in_rand_and_no_formulas(): void
    {
        $this->med(['price_cents' => 29950, 'repeat_cycle_days' => 28, 'is_subscription' => true, 'name' => '=HYPERLINK("http://evil")']);
        $this->med(['name' => 'Off', 'nappi_code' => 'N2', 'is_active' => false]);

        $csv = $this->io()->export($this->ops);
        $lines = array_map('str_getcsv', array_filter(explode("\n", $csv)));

        $this->assertSame(ProductCatalogIO::COLUMNS, $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $row = array_combine($lines[0], collect($lines)->first(fn ($l) => ($l[0] ?? '') === 'N100'));
        $this->assertSame('299.50', $row['price_rand']);
        $this->assertSame('yes', $row['is_subscription']);

        $active = $this->io()->export($this->ops, true);
        $this->assertStringNotContainsString(',Off,', $active);
    }

    public function test_what_you_export_you_can_load_back_unchanged(): void
    {
        $this->med(['price_cents' => 29950, 'repeat_cycle_days' => 28, 'is_subscription' => true, 'category' => 'weight-loss']);
        $this->med(['name' => 'Beta', 'nappi_code' => 'N2', 'price_cents' => null]);
        $path = $this->dir.DIRECTORY_SEPARATOR.'round.csv';
        file_put_contents($path, $this->io()->export($this->ops));

        $r = $this->io()->import($this->ops, $path);

        $this->assertSame([0, 0, 2], [$r['created'], $r['updated'], $r['unchanged']]);
    }

    public function test_only_operations_can_import_or_export(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
            $u = User::factory()->create(['role' => $role]);
            foreach ([fn () => $this->io()->export($u), fn () => $this->io()->preview($u, $this->csv([self::HEAD])), fn () => $this->io()->import($u, $this->csv([self::HEAD]))] as $try) {
                try {
                    $try();
                    $this->fail('Expected 403');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $this->assertSame(403, $e->getStatusCode());
                }
            }
            $this->actingAs($u)->get(route('admin.product-list'))->assertForbidden();
            $this->actingAs($u)->get(route('admin.product-list.export'))->assertForbidden();
        }
    }

    public function test_the_screen_checks_then_loads_the_file(): void
    {
        $this->med(['price_cents' => 100]);
        $this->actingAs($this->ops)->get(route('admin.product-list'))->assertOk()->assertSee('Product list');
        $this->actingAs($this->ops)->get(route('admin.product-list.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $bad = UploadedFile::fake()->createWithContent('bad.csv', "name,form,strength,price_rand\nX,tablet,1mg,cheap\n");
        Livewire::actingAs($this->ops)->test(ProductListImport::class)
            ->call('check')->assertHasErrors('file')
            ->set('file', $bad)->call('check')->assertSeeHtml('data-has-errors')->assertDontSeeHtml('data-action="apply"');

        $good = UploadedFile::fake()->createWithContent('good.csv', "nappi_code,name,form,strength,price_rand\nN100,Alpha,tablet,5mg,50\nN500,Fresh,tablet,1mg,9\n");
        Livewire::actingAs($this->ops)->test(ProductListImport::class)
            ->set('file', $good)->call('check')->assertSeeHtml('data-action="apply"')->assertSeeHtml('<strong>1</strong> new')
            ->call('apply')->assertSeeHtml('data-done');

        $this->assertSame(5000, Medication::where('nappi_code', 'N100')->value('price_cents'));
        $this->assertSame(1, Medication::where('nappi_code', 'N500')->count());
    }
}
