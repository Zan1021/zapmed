<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparImports;
use Zapmed\SparCore\Models\SparImportBatch;

/**
 * Regression: the Imports admin screen must render when import batches EXIST.
 *
 * The prior smoke test only hit the route with an EMPTY table, so the
 * `->with('importedBy')` eager-load never resolved (Eloquent skips loading a
 * relation when there are no parent rows). With ≥1 batch present it threw
 * RelationNotFoundException — which Livewire surfaces as the misleading
 * "This page has expired" dialog. This test seeds a batch to catch that.
 */
class SparImportsScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@t.test',
            'password' => Hash::make('x'), 'role' => 'admin',
            'spar_pharmacy_id' => null, 'is_active' => true,
        ]);
    }

    public function test_imports_screen_renders_with_existing_batches(): void
    {
        // Seed a real batch — this is the condition the live app was in.
        SparImportBatch::create([
            'filename' => 'SalesExtract072026.csv',
            'source' => 'manual',
            'status' => 'completed',
            'records_total' => 6,
            'records_processed' => 6,
            'records_created' => 6,
            'imported_by' => 1, // plain column, no relation
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin());

        // Both the HTTP route AND the Livewire render must succeed (no 500).
        $this->get(route('admin.spar.imports'))->assertOk();

        Livewire::test(SparImports::class)
            ->assertOk()
            ->assertSee('SalesExtract072026.csv');
    }
}
