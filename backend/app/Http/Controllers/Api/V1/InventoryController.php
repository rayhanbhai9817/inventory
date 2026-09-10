<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryRowResource;
use App\Http\Resources\StockBatchResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'batches'])
            ->withSum(['movements as total_stock_in' => fn ($q) => $q->where('type', 'stock_in')], 'units')
            ->withSum(['movements as total_stock_out' => fn ($q) => $q->where('type', 'stock_out')], 'units')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }));

        $allProducts = (clone $query)->get();
        $totalBoxes = $allProducts->sum(fn ($p) => $p->batches->where('status', '!=', 'depleted')
            ->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1)));
        $totalUnits = $allProducts->sum(fn ($p) => $p->batches->where('status', '!=', 'depleted')->sum('remaining_units'));
        $lowStockCount = $allProducts->filter(function ($p) {
            $units = $p->batches->where('status', '!=', 'depleted')->sum('remaining_units');

            return $p->min_stock_level > 0 && $units > 0 && $units <= $p->min_stock_level;
        })->count();
        $outOfStockCount = $allProducts->filter(
            fn ($p) => $p->batches->where('status', '!=', 'depleted')->sum('remaining_units') <= 0
        )->count();

        $remainingExpr = '(select coalesce(sum(remaining_units), 0) from stock_batches '.
            'where stock_batches.product_id = products.id and stock_batches.status != \'depleted\')';

        $products = $query
            ->when($request->string('status')->toString(), function ($q) use ($request, $remainingExpr) {
                match ($request->string('status')->toString()) {
                    'out_of_stock' => $q->whereRaw("{$remainingExpr} <= 0"),
                    'low_stock' => $q->whereRaw("{$remainingExpr} > 0")
                        ->where('min_stock_level', '>', 0)
                        ->whereRaw("{$remainingExpr} <= min_stock_level"),
                    'in_stock' => $q->whereRaw("{$remainingExpr} > 0")
                        ->where(function ($q) use ($remainingExpr) {
                            $q->where('min_stock_level', '<=', 0)
                                ->orWhereRaw("{$remainingExpr} > min_stock_level");
                        }),
                    default => null,
                };
            })
            ->orderBy('name');

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            return $this->exportCsv($products->get());
        }

        $products = $products->paginate($request->integer('per_page', 15));

        return InventoryRowResource::collection($products)->additional([
            'summary' => [
                'total_products' => $allProducts->count(),
                'total_boxes' => round($totalBoxes, 2),
                'available_units' => (int) $totalUnits,
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'batches' => fn ($q) => $q->orderBy('received_at')->orderBy('id')]);

        $activeBatches = $product->batches->where('status', '!=', 'depleted');
        $totalUnits = (int) $activeBatches->sum('remaining_units');
        $totalBoxes = round($activeBatches->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1)), 2);

        $totalStockIn = (int) $product->movements()->where('type', 'stock_in')->sum('units');
        $totalStockOut = (int) $product->movements()->where('type', 'stock_out')->sum('units');
        $lastMovement = $product->movements()->latest('created_at')->first();

        $recentMovements = $product->movements()
            ->with(['batchLinks.batch', 'user'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                ],
                'inventory_summary' => [
                    'total_boxes' => $totalBoxes,
                    'total_units' => $totalUnits,
                    'status' => match (true) {
                        $totalUnits <= 0 => 'out_of_stock',
                        $totalUnits <= $product->min_stock_level && $product->min_stock_level > 0 => 'low_stock',
                        default => 'in_stock',
                    },
                ],
                'movement_stats' => [
                    'total_stock_in' => $totalStockIn,
                    'total_stock_out' => $totalStockOut,
                    'current_balance' => $totalUnits,
                    'last_updated' => $lastMovement?->created_at,
                ],
                'batches' => StockBatchResource::collection($product->batches),
                'recent_movements' => StockMovementResource::collection($recentMovements),
            ],
        ]);
    }

    private function exportCsv($products): StreamedResponse
    {
        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Product', 'Category', 'Boxes', 'Units/Box', 'Total Units', 'Stock In', 'Stock Out', 'Status']);
            foreach ($products as $product) {
                $row = (new InventoryRowResource($product))->toArray(request());
                fputcsv($handle, [
                    $row['sku'], $row['name'], $row['category'], $row['boxes'],
                    $row['units_per_box'], $row['total_units'], $row['stock_in_total'],
                    $row['stock_out_total'], $row['status'],
                ]);
            }
            fclose($handle);
        }, 'inventory.csv', ['Content-Type' => 'text/csv']);
    }
}
