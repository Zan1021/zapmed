<?php

namespace Tests\Feature;

use App\Models\CatalogCoupon;
use App\Models\CatalogItem;
use App\Models\ImportQuarantine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Contro\ControParityReport;
use App\Services\Contro\ControPullService;
use App\Services\Contro\ControClient;
use App\Services\Contro\ControReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task 9 — full end-to-end Contro import against a realistic multi-entity fixture, driven through the
 * real pipeline: pull (Http::fake) -> reconcile -> parity. Asserts correctness, dependency resolution,
 * idempotency across the whole flow, and ZERO side-effects.
 */
class ControEndToEndImportTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contro.api.base_url', 'https://contro.example');
        config()->set('contro.api.email', 'svc@zapmed');
        config()->set('contro.api.password', 'secret');
        config()->set('contro.api.page_size', 100);

        $this->data = json_decode(file_get_contents(base_path('tests/Fixtures/Contro/sample-dataset.json')), true);
    }

    /** Fake auth + each entity endpoint returning its fixture rows (then an empty page to end paging). */
    private function fakeContro(): void
    {
        $fakes = ['https://contro.example/api/crm/auth/login' => Http::response(['token' => 'tok'], 200)];

        $paths = [
            'products' => 'products', 'patients' => 'patients', 'coupons' => 'coupons',
            'orders' => 'orders', 'order-status-history' => 'order_status_history',
            'payments' => 'payments', 'prescriptions' => 'prescriptions',
        ];

        foreach ($paths as $urlPart => $key) {
            $rows = $this->data[$key] ?? [];
            $fakes["https://contro.example/api/crm/{$urlPart}*"] = Http::sequence()
                ->push($rows, 200)
                ->whenEmpty(Http::response([], 200));
        }

        Http::fake($fakes);
    }

    private function runPipeline(): void
    {
        $service = new ControPullService(ControClient::fromConfig());
        $service->pullAll('manual', true);
        (new ControReconciler())->reconcileAll();
    }

    public function test_full_import_lands_all_entities_correctly(): void
    {
        $this->fakeContro();
        $this->runPipeline();

        // Patients -> users (upsert, real email) + profiles + address.
        $this->assertSame(2, PatientProfile::where('upstream_source', 'contro')->count());
        $priya = User::where('upstream_id', 'pat_priya')->first();
        $this->assertSame('priya@example.com', $priya->email);
        $this->assertSame(1, $priya->patientProfile->addresses()->count());

        // Doctor referenced by order/prescription but never in patients feed -> stub principal.
        $doc = User::where('upstream_id', 'doc_smith')->first();
        $this->assertNotNull($doc);
        $this->assertFalse((bool) $doc->is_active);

        // Catalog + coupons.
        $this->assertSame(2, CatalogItem::where('upstream_source', 'contro')->count());
        $this->assertSame(12100, CatalogItem::where('upstream_id', '1')->first()->price_minor);
        $this->assertSame(1, CatalogCoupon::where('upstream_id', 'coup_1')->first()->usages()->count());

        // Orders + immutable history.
        $this->assertSame(2, Order::where('upstream_source', 'contro')->count());
        $o1 = Order::where('contro_order_number', 'CTRL-1001')->first();
        $this->assertSame('Processing', $o1->status);
        $this->assertSame(5000, $o1->service_fee_minor);
        $this->assertSame(3, OrderStatusHistory::count());

        // Payments: pay_1 resolves own patient; pay_2 (no hash, zero amount) resolves via order CTRL-1002.
        $this->assertSame(2, Payment::where('upstream_source', 'contro')->count());
        $pay2 = Payment::where('upstream_id', 'pay_2')->first();
        $this->assertSame(0, $pay2->amount);
        $this->assertSame('payfast', $pay2->provider);
        $this->assertSame(User::where('upstream_id', 'pat_johan')->first()->id, $pay2->patient_id);

        // Prescription + 2 medication lines with rands->cents conversion.
        $rx = Prescription::where('upstream_id', 'rx_1')->first();
        $this->assertSame(20650, $rx->total_medication_cost_minor);
        $this->assertSame(2, $rx->items()->count());
        $this->assertSame(403, $rx->items()->where('medication_name', 'Metformin 500')->first()->unit_price);

        // No anomalies for this clean dataset.
        $this->assertSame(0, ImportQuarantine::where('status', 'open')->count());
    }

    public function test_parity_is_clean_after_import(): void
    {
        $this->fakeContro();
        $this->runPipeline();

        $report = new ControParityReport();
        $this->assertTrue($report->isClean(), 'parity should be clean for the sample dataset');

        $counts = $report->build();
        $this->assertSame(2, $counts['patients']['reconciled']);
        $this->assertSame(2, $counts['orders']['reconciled']);
        $this->assertSame(3, $counts['order_status_history']['reconciled']);
        $this->assertSame(2, $counts['payments']['reconciled']);
    }

    public function test_pipeline_is_idempotent_when_run_twice(): void
    {
        $this->fakeContro();
        $this->runPipeline();
        // Re-fake (sequences are consumed) and run the WHOLE pipeline again.
        $this->fakeContro();
        $this->runPipeline();

        // Counts unchanged — no duplicates anywhere.
        $this->assertSame(2, PatientProfile::where('upstream_source', 'contro')->count());
        $this->assertSame(2, CatalogItem::where('upstream_source', 'contro')->count());
        $this->assertSame(2, Order::where('upstream_source', 'contro')->count());
        $this->assertSame(3, OrderStatusHistory::count());
        $this->assertSame(2, Payment::where('upstream_source', 'contro')->count());
        $this->assertSame(1, Prescription::where('upstream_source', 'contro')->count());
        $this->assertSame(2, Prescription::where('upstream_id', 'rx_1')->first()->items()->count());
    }

    public function test_full_import_fires_no_side_effects(): void
    {
        Mail::fake();
        Bus::fake();
        Notification::fake();

        $this->fakeContro();
        $this->runPipeline();

        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        Notification::assertNothingSent();
    }
}
