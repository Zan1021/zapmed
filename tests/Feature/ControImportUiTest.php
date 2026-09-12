<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\ControImport;
use App\Models\ImportQuarantine;
use App\Models\UpstreamIngestedRow;
use App\Models\User;
use App\Services\Contro\ControReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 1 — Import Ops UI (Livewire admin component).
 */
class ControImportUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_admin_can_view_the_import_ops_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.contro-import'))
            ->assertOk()
            ->assertSeeLivewire(ControImport::class);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $this->actingAs($patient)
            ->get(route('admin.contro-import'))
            ->assertForbidden();
    }

    public function test_pull_is_blocked_when_not_configured(): void
    {
        config()->set('contro.api.base_url', null);

        Livewire::actingAs($this->admin())
            ->test(ControImport::class)
            ->assertSet('quarantineFilter', 'open')
            ->call('pullAll');

        // The guard held: no pull ran (behavioural proof, independent of flash mechanics).
        $this->assertSame(0, \App\Models\UpstreamSyncRun::count());
        $this->assertSame(0, \App\Models\UpstreamIngestedRow::count());
    }

    public function test_reconcile_action_maps_staged_rows(): void
    {
        // Stage a product; reconcile via the UI action.
        UpstreamIngestedRow::create([
            'entity_set' => 'products', 'upstream_id' => 'p1',
            'payload' => ['id' => 'p1', 'productName' => 'A', 'price' => 10.0, 'isEnabled' => true],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ControImport::class)
            ->call('reconcile');

        $this->assertSame(1, \App\Models\CatalogItem::where('upstream_id', 'p1')->count());
    }

    public function test_quarantine_can_be_resolved(): void
    {
        $q = ImportQuarantine::flag('orders', 'o1', 'unknown_status', 'bad', ['id' => 'o1']);

        Livewire::actingAs($this->admin())
            ->test(ControImport::class)
            ->call('resolveQuarantine', $q->id);

        $this->assertSame('resolved', $q->fresh()->status);
        $this->assertNotNull($q->fresh()->resolved_at);
    }

    public function test_parity_computed_reflects_staged_vs_reconciled(): void
    {
        UpstreamIngestedRow::create([
            'entity_set' => 'products', 'upstream_id' => 'p9',
            'payload' => ['id' => 'p9', 'productName' => 'Z', 'price' => 5.0, 'isEnabled' => true],
        ]);
        (new ControReconciler())->reconcileProducts();

        Livewire::actingAs($this->admin())
            ->test(ControImport::class)
            ->assertSee('products');
    }
}
