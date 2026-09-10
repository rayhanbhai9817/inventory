<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
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
            ],
        ]);
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
