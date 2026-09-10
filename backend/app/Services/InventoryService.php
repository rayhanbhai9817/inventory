<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The FIFO batch inventory engine. Every function here runs inside a
 * database transaction and locks the batches it touches
 * (`lockForUpdate`) so two concurrent Stock OUT / adjustment requests
 * for the same product cannot both read the same "available" balance
 * and jointly over-consume it. On MySQL (the production target) this
 * is a real row-level lock; on SQLite (used for local dev/tests) the
 * database serializes writes at a coarser grain, so the *logic* is
 * verified by tests here but genuine concurrent-process load should be
 * exercised against MySQL — see docs/PROGRESS.md.
 */
class InventoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function stockIn(
        Product $product,
        int $boxes,
        int $unitsPerBox,
        string $receivedAt,
        ?string $notes,
        User $user,
        ?int $supplierId = null,
    ): StockMovement {
        if ($boxes <= 0 || $unitsPerBox <= 0) {
            throw new InvalidArgumentException('Boxes and units per box must both be greater than zero.');
        }

        return DB::transaction(function () use ($product, $boxes, $unitsPerBox, $receivedAt, $notes, $user, $supplierId) {
            $balanceBefore = $product->totalRemainingUnits();
            $totalUnits = $boxes * $unitsPerBox;

            $batch = StockBatch::create([
                'product_id' => $product->id,
                'supplier_id' => $supplierId,
                'batch_code' => $this->uniqueBatchCode(),
                'boxes' => $boxes,
                'units_per_box' => $unitsPerBox,
                'total_units' => $totalUnits,
                'remaining_units' => $totalUnits,
                'status' => 'full',
                'received_at' => $receivedAt,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => 'stock_in',
                'units' => $totalUnits,
                'balance_after' => $balanceBefore + $totalUnits,
                'note' => $notes,
                'user_id' => $user->id,
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'stock_batch_id' => $batch->id,
                'units' => $totalUnits,
            ]);

            $this->auditLogger->log('stock_in', $product, null, [
                'batch_code' => $batch->batch_code,
                'boxes' => $boxes,
                'units_per_box' => $unitsPerBox,
                'total_units' => $totalUnits,
                'supplier_id' => $supplierId,
            ], $user);

            return $movement->load('batchLinks.batch');
        });
    }

    public function stockOut(Product $product, int $units, ?string $notes, User $user): StockMovement
    {
        if ($units <= 0) {
            throw new InvalidArgumentException('Units must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $units, $notes, $user) {
            $balanceBefore = $product->totalRemainingUnits();
            $consumed = $this->consumeFifo($product, $units);
            $balanceAfter = $balanceBefore - $units;

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => 'stock_out',
                'units' => $units,
                'balance_after' => $balanceAfter,
                'note' => $notes,
                'user_id' => $user->id,
            ]);

            foreach ($consumed as $link) {
                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'stock_batch_id' => $link['batch']->id,
                    'units' => $link['units'],
                ]);
            }

            $this->auditLogger->log('stock_out', $product, null, [
                'units' => $units,
                'batches_consumed' => array_map(
                    fn ($l) => ['batch_code' => $l['batch']->batch_code, 'units' => $l['units']],
                    $consumed
                ),
            ], $user);

            $this->notifications->checkStockThresholds($product, $balanceBefore, $balanceAfter);

            return $movement->load('batchLinks.batch');
        });
    }

    public function adjust(
        Product $product,
        string $direction,
        int $quantity,
        string $reason,
        ?string $note,
        User $user,
    ): StockAdjustment {
        if (! in_array($direction, ['increase', 'decrease'], true)) {
            throw new InvalidArgumentException('Direction must be "increase" or "decrease".');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $direction, $quantity, $reason, $note, $user) {
            $balanceBefore = $product->totalRemainingUnits();

            if ($direction === 'increase') {
                $batch = StockBatch::create([
                    'product_id' => $product->id,
                    'batch_code' => $this->uniqueBatchCode(),
                    'boxes' => 1,
                    'units_per_box' => $quantity,
                    'total_units' => $quantity,
                    'remaining_units' => $quantity,
                    'status' => 'full',
                    'received_at' => now()->toDateString(),
                    'notes' => "Stock adjustment ({$reason})",
                    'created_by' => $user->id,
                ]);
                $links = [['batch' => $batch, 'units' => $quantity]];
                $balanceAfter = $balanceBefore + $quantity;
            } else {
                $links = $this->consumeFifo($product, $quantity);
                $balanceAfter = $balanceBefore - $quantity;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => $direction === 'increase' ? 'adjustment_increase' : 'adjustment_decrease',
                'units' => $quantity,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'user_id' => $user->id,
            ]);

            foreach ($links as $link) {
                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'stock_batch_id' => $link['batch']->id,
                    'units' => $link['units'],
                ]);
            }

            $adjustment = StockAdjustment::create([
                'product_id' => $product->id,
                'stock_movement_id' => $movement->id,
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
                'note' => $note,
                'user_id' => $user->id,
            ]);

            $this->auditLogger->log('stock_adjustment', $product, null, [
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
            ], $user);

            $this->notifications->checkStockThresholds($product, $balanceBefore, $balanceAfter);
            $this->notifications->adminActivity(
                $product,
                sprintf('Stock adjustment (%s) on %s: %s %d units.', $reason, $product->name, $direction, $quantity)
            );

            return $adjustment->load('movement.batchLinks.batch');
        });
    }

    /**
     * Locks and consumes oldest-first batches for $units. Throws
     * InsufficientStockException without writing anything if the
     * product doesn't have enough stock — never allows negative
     * inventory.
     *
     * @return array<int, array{batch: StockBatch, units: int}>
     */
    private function consumeFifo(Product $product, int $units): array
    {
        $batches = StockBatch::query()
            ->where('business_id', Tenant::id())
            ->where('product_id', $product->id)
            ->where('status', '!=', 'depleted')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $batches->sum('remaining_units');

        if ($available < $units) {
            throw new InsufficientStockException($product, $units, $available);
        }

        $remaining = $units;
        $links = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($batch->remaining_units, $remaining);
            if ($take <= 0) {
                continue;
            }

            $batch->remaining_units -= $take;
            $batch->refreshStatus();
            $batch->save();

            $links[] = ['batch' => $batch, 'units' => $take];
            $remaining -= $take;
        }

        return $links;
    }

    private function uniqueBatchCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (StockBatch::where('batch_code', $code)->exists());

        return $code;
    }
}
