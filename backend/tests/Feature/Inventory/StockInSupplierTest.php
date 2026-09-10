<?php

namespace Tests\Feature\Inventory;

use App\Models\Business;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the spec's "CORE requirement": Stock IN must be able to record
 * which supplier a batch came from, and that supplier must be traceable
 * through the batch/ledger/activity — while never affecting FIFO
 * quantity logic itself.
 */
class StockInSupplierTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Product $product;

    private Supplier $supplier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $business = Business::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::set($business->id);

        $this->user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'password1234',
        ]);

        $this->product = Product::create([
            'name' => 'Widget',
            'sku' => 'W-1',
            'min_stock_level' => 50,
            'created_by' => $this->user->id,
        ]);

        $this->supplier = Supplier::create(['name' => 'Global Supply Co']);

        $this->inventory = app(InventoryService::class);
    }

    public function test_stock_in_records_the_supplier_on_the_batch(): void
    {
        $movement = $this->inventory->stockIn(
            $this->product, 5, 100, '2026-01-01', 'first delivery', $this->user, $this->supplier->id
        );

        $batch = $movement->batchLinks->first()->batch->fresh();

        $this->assertSame($this->supplier->id, $batch->supplier_id);
        $this->assertSame($this->supplier->id, $batch->supplier->id);
        $this->assertTrue($this->supplier->batches()->where('id', $batch->id)->exists());
    }

    public function test_stock_in_supplier_is_optional(): void
    {
        $movement = $this->inventory->stockIn($this->product, 1, 10, '2026-01-01', null, $this->user);

        $batch = $movement->batchLinks->first()->batch->fresh();
        $this->assertNull($batch->supplier_id);
    }

    public function test_supplier_never_affects_inventory_quantity_or_fifo_order(): void
    {
        $withSupplier = $this->inventory->stockIn($this->product, 1, 300, '2026-01-01', null, $this->user, $this->supplier->id);
        $withoutSupplier = $this->inventory->stockIn($this->product, 1, 200, '2026-01-02', null, $this->user);

        $this->assertSame(500, $this->product->totalRemainingUnits());

        // FIFO still consumes oldest (with-supplier) batch first, regardless of supplier presence.
        $this->inventory->stockOut($this->product, 300, null, $this->user);

        $batchWithSupplier = $withSupplier->batchLinks->first()->batch->fresh();
        $batchWithoutSupplier = $withoutSupplier->batchLinks->first()->batch->fresh();

        $this->assertSame(0, $batchWithSupplier->remaining_units);
        $this->assertSame(200, $batchWithoutSupplier->remaining_units);
        $this->assertSame(200, $this->product->totalRemainingUnits());
    }

    public function test_stock_in_endpoint_accepts_and_persists_supplier_id(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme Retail',
            'name' => 'Owner',
            'email' => 'owner2@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        $token = $register->json('token');
        $businessId = $register->json('business.id');
        Tenant::set($businessId);

        $product = $this->asBearerToken($token)
            ->postJson('/api/v1/products', ['name' => 'Gadget', 'sku' => 'G-1'])
            ->assertCreated();

        $supplier = $this->asBearerToken($token)
            ->postJson('/api/v1/suppliers', ['name' => 'ACME Parts'])
            ->assertCreated();

        $this->asBearerToken($token)
            ->postJson('/api/v1/stock/in', [
                'product_id' => $product->json('data.id'),
                'boxes' => 2,
                'units_per_box' => 10,
                'received_at' => now()->toDateString(),
                'supplier_id' => $supplier->json('data.id'),
            ])->assertCreated();

        $batches = $this->asBearerToken($token)
            ->getJson('/api/v1/batches?product_id='.$product->json('data.id'))
            ->assertOk();

        $batches->assertJsonPath('data.0.supplier.id', $supplier->json('data.id'));
    }
}
