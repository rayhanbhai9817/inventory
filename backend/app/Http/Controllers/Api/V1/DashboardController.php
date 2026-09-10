<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($request);

        $products = Product::query()->active()->with('batches')->get();
        $currentTotalUnits = (int) $products->sum(fn ($p) => $p->batches->where('status', '!=', 'depleted')->sum('remaining_units'));

        $netFlowDuringPeriod = $this->netFlow($periodStart, $periodEnd);
        $netFlowAfterPeriodEnd = $this->netFlow($periodEnd->copy()->addSecond(), null);

        $closing = $currentTotalUnits - $netFlowAfterPeriodEnd;
        $opening = $closing - $netFlowDuringPeriod;

        $stockInTotal = (int) StockMovement::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('type', ['stock_in', 'adjustment_increase'])
            ->sum('units');

        $stockOutTotal = (int) StockMovement::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('type', ['stock_out', 'adjustment_decrease'])
            ->sum('units');

        $lowStockCount = $products->filter(function ($p) {
            $units = $p->batches->where('status', '!=', 'depleted')->sum('remaining_units');

            return $p->min_stock_level > 0 && $units > 0 && $units <= $p->min_stock_level;
        })->count();

        $outOfStockCount = $products->filter(
            fn ($p) => $p->batches->where('status', '!=', 'depleted')->sum('remaining_units') <= 0
        )->count();

        $healthyCount = $products->count() - $lowStockCount - $outOfStockCount;
        $healthPercent = $products->count() > 0
            ? round(($healthyCount / $products->count()) * 100)
            : 100;

        $totalBoxes = round($products->sum(
            fn ($p) => $p->batches->where('status', '!=', 'depleted')->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1))
        ), 2);

        $recentMovements = StockMovement::query()
            ->with(['product', 'user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $productsMissingSupplier = Product::query()->active()->doesntHave('suppliers')->get(['id', 'name', 'sku']);
        $productsMissingPrice = Product::query()->active()->doesntHave('prices')->get(['id', 'name', 'sku']);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $periodStart->toDateString(),
                    'to' => $periodEnd->toDateString(),
                ],
                'period_stock_summary' => [
                    'opening' => max($opening, 0),
                    'net_flow' => $netFlowDuringPeriod,
                    'closing' => $closing,
                ],
                'stock_in_total' => $stockInTotal,
                'stock_out_total' => $stockOutTotal,
                'totals' => [
                    'total_products' => $products->count(),
                    'total_units' => $currentTotalUnits,
                    'total_boxes' => $totalBoxes,
                ],
                'inventory_health' => [
                    'percent' => $healthPercent,
                    'status' => $healthPercent >= 90 ? 'optimal' : ($healthPercent >= 70 ? 'fair' : 'attention_needed'),
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                ],
                'recent_movements' => StockMovementResource::collection($recentMovements),
                'supplier_summary' => $this->supplierSummary($periodStart, $periodEnd),
                'product_alerts' => [
                    'missing_supplier_count' => $productsMissingSupplier->count(),
                    'missing_supplier_sample' => $productsMissingSupplier->take(5)->map(fn ($p) => [
                        'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
                    ])->values(),
                    'missing_price_count' => $productsMissingPrice->count(),
                    'missing_price_sample' => $productsMissingPrice->take(5)->map(fn ($p) => [
                        'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
                    ])->values(),
                ],
            ],
        ]);
    }

    /**
     * Supplier KPIs. Purely counts/quantities derived from suppliers and
     * stock_batches — no pricing or financial data involved.
     */
    private function supplierSummary(Carbon $periodStart, Carbon $periodEnd): array
    {
        $totalSuppliers = Supplier::query()->notArchived()->count();
        $activeSuppliers = Supplier::query()->active()->count();

        $recentlyUsedSuppliers = StockBatch::query()
            ->whereNotNull('supplier_id')
            ->whereDate('received_at', '>=', $periodStart->toDateString())
            ->whereDate('received_at', '<=', $periodEnd->toDateString())
            ->distinct('supplier_id')
            ->count('supplier_id');

        $topSuppliers = Supplier::query()
            ->notArchived()
            ->withSum('batches as total_units_supplied', 'total_units')
            ->orderByDesc('total_units_supplied')
            ->limit(5)
            ->get()
            ->filter(fn ($s) => $s->total_units_supplied > 0)
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'total_units_supplied' => (int) $s->total_units_supplied,
            ])
            ->values();

        return [
            'total_suppliers' => $totalSuppliers,
            'active_suppliers' => $activeSuppliers,
            'recently_used_suppliers' => $recentlyUsedSuppliers,
            'top_suppliers_by_quantity' => $topSuppliers,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->string('period', 'today')->toString();
        $now = Carbon::now();

        return match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'custom' => [
                $request->date('from') ? $request->date('from')->startOfDay() : $now->copy()->startOfDay(),
                $request->date('to') ? $request->date('to')->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function netFlow(?Carbon $from, ?Carbon $to): int
    {
        $query = StockMovement::query()->where('business_id', Tenant::id());

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $inSum = (clone $query)->whereIn('type', ['stock_in', 'adjustment_increase'])->sum('units');
        $outSum = (clone $query)->whereIn('type', ['stock_out', 'adjustment_decrease'])->sum('units');

        return (int) $inSum - (int) $outSum;
    }
}
