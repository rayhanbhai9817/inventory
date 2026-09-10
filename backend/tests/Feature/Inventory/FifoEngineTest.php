<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FifoEngineTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Product $product;

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

        $this->inventory = app(InventoryService::class);
    }

    public function test_stock_in_creates_a_full_batch_and_a_movement(): void
    {
        $movement = $this->inventory->stockIn($this->product, 5, 100, '2026-01-01', 'first delivery', $this->user);

        $this->assertSame('stock_in', $movement->type);
        $this->assertSame(500, $movement->units);
        $this->assertSame(500, $movement->balance_after);
        $this->assertSame(500, $this->product->totalRemainingUnits());

        $batch = $this->product->batches()->first();
        $this->assertSame(5, $batch->boxes);
        $this->assertSame(100, $batch->units_per_box);
        $this->assertSame(500, $batch->total_units);
        $this->assertSame(500, $batch->remaining_units);
        $this->assertSame('full', $batch->status);
    }

    public function test_stock_out_consumes_a_single_batch_partially(): void
    {
        $this->inventory->stockIn($this->product, 5, 100, '2026-01-01', null, $this->user);
        $movement = $this->inventory->stockOut($this->product, 200, 'sold', $this->user);

        $this->assertSame(200, $movement->units);
        $this->assertSame(300, $movement->balance_after);

        $batch = $this->product->batches()->first();
        $this->assertSame(300, $batch->remaining_units);
        $this->assertSame('partial', $batch->status);
    }

    public function test_stock_out_consumes_oldest_batch_first_and_depletes_it(): void
    {
        $old = $this->inventory->stockIn($this->product, 1, 500, '2026-01-01', null, $this->user);
        $new = $this->inventory->stockIn($this->product, 1, 1000, '2026-01-10', null, $this->user);

        $movement = $this->inventory->stockOut($this->product, 700, null, $this->user);

        $oldBatch = $old->batchLinks->first()->batch->fresh();
        $newBatch = $new->batchLinks->first()->batch->fresh();

        $this->assertSame(0, $oldBatch->remaining_units);
        $this->assertSame('depleted', $oldBatch->status);
        $this->assertSame(800, $newBatch->remaining_units);
        $this->assertSame('partial', $newBatch->status);

        // Movement is traceable to both batches it touched.
        $this->assertCount(2, $movement->batchLinks);
        $this->assertSame(700, $movement->batchLinks->sum('units'));
    }

    /**
     * The exact scenario from the spec: Batch A=300, B=500, C=700 (oldest
     * to newest); Stock OUT 600 must leave A=0, B=200, C=700.
     */
    public function test_spec_three_batch_fifo_example(): void
    {
        $a = $this->inventory->stockIn($this->product, 1, 300, '2026-01-01', null, $this->user);
        $b = $this->inventory->stockIn($this->product, 1, 500, '2026-01-02', null, $this->user);
        $c = $this->inventory->stockIn($this->product, 1, 700, '2026-01-03', null, $this->user);

        $this->inventory->stockOut($this->product, 600, null, $this->user);

        $batchA = $a->batchLinks->first()->batch->fresh();
        $batchB = $b->batchLinks->first()->batch->fresh();
        $batchC = $c->batchLinks->first()->batch->fresh();

        $this->assertSame(0, $batchA->remaining_units);
        $this->assertSame(200, $batchB->remaining_units);
        $this->assertSame(700, $batchC->remaining_units);
        $this->assertSame(900, $this->product->totalRemainingUnits());
    }

    public function test_stock_out_never_allows_negative_inventory(): void
    {
        $this->inventory->stockIn($this->product, 1, 100, '2026-01-01', null, $this->user);

        $this->expectException(InsufficientStockException::class);

        try {
            $this->inventory->stockOut($this->product, 101, null, $this->user);
        } finally {
            // Nothing should have been written — balance stays exactly 100.
            $this->assertSame(100, $this->product->totalRemainingUnits());
            $this->assertSame(0, $this->product->movements()->where('type', 'stock_out')->count());
        }
    }

    public function test_adjustment_increase_creates_a_batch_and_movement(): void
    {
        $adjustment = $this->inventory->adjust($this->product, 'increase', 50, 'found', 'shelf recount', $this->user);

        $this->assertSame('increase', $adjustment->direction);
        $this->assertSame(50, $this->product->totalRemainingUnits());
        $this->assertSame('adjustment_increase', $adjustment->movement->type);
    }

    public function test_adjustment_decrease_consumes_fifo_and_rejects_insufficient_stock(): void
    {
        $this->inventory->stockIn($this->product, 1, 30, '2026-01-01', null, $this->user);

        $adjustment = $this->inventory->adjust($this->product, 'decrease', 10, 'damaged', null, $this->user);
        $this->assertSame(20, $this->product->totalRemainingUnits());

        $this->expectException(InsufficientStockException::class);
        $this->inventory->adjust($this->product, 'decrease', 999, 'damaged', null, $this->user);
    }

    public function test_movement_reference_is_deterministic_and_type_prefixed(): void
    {
        $in = $this->inventory->stockIn($this->product, 1, 10, '2026-01-01', null, $this->user);
        $out = $this->inventory->stockOut($this->product, 5, null, $this->user);

        $this->assertStringStartsWith('SI-', $in->reference());
        $this->assertStringStartsWith('SO-', $out->reference());
    }
}
