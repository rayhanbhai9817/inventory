<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\ProductPriceRequest;
use App\Http\Resources\ProductPriceResource;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reference-price management. Deliberately separate from InventoryService
 * and never writes to stock_batches / stock_movements / stock_ledger —
 * see ProductPrice model docblock for the full separation rationale.
 */
class ProductPriceController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Full price history for one product, newest first.
     */
    public function index(Product $product)
    {
        $prices = $product->prices()
            ->with('creator')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(15);

        return ProductPriceResource::collection($prices);
    }

    /**
     * Records a new price entry. Never overwrites or removes prior
     * entries — the ledger is append-only, so "changing the price" means
     * adding a new row with today's (or a backdated/future) effective_date.
     */
    public function store(ProductPriceRequest $request, Product $product): JsonResponse
    {
        $price = $product->prices()->create([
            ...$request->validated(),
            'currency' => $request->validated()['currency'] ?? 'USD',
            'created_by' => $request->user()->id,
        ]);

        $this->auditLogger->log('product_price_recorded', $product, null, [
            'price' => (string) $price->price,
            'currency' => $price->currency,
            'effective_date' => $price->effective_date->format('Y-m-d'),
        ], $request->user());

        return response()->json(['data' => new ProductPriceResource($price->load('creator'))], 201);
    }

    /**
     * Edits only the `notes` on an existing price entry — the price and
     * effective_date themselves are immutable once recorded.
     */
    public function update(Request $request, Product $product, ProductPrice $price)
    {
        abort_if($price->product_id !== $product->id, 404);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $old = $price->only(['notes']);
        $price->update($data);

        $this->auditLogger->log('product_price_note_updated', $product, $old, $data, $request->user());

        return new ProductPriceResource($price->load('creator'));
    }

    /**
     * Price Catalog: current (latest) price per product, with search /
     * category / status filters and CSV export. This is the report the
     * spec calls the "Product Price Report".
     */
    public function catalog(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'prices' => fn ($q) => $q->orderByDesc('effective_date')->orderByDesc('id')->limit(1)])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->active())
            ->when($request->string('status')->toString() === 'archived', fn ($q) => $q->archived())
            ->when($request->boolean('missing_price'), fn ($q) => $q->doesntHave('prices'))
            ->orderBy('name');

        $products = $query->paginate($request->integer('per_page', 15));

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            return $this->exportCsv($products->getCollection());
        }

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => $this->catalogRow($product)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    private function catalogRow(Product $product): array
    {
        $current = $product->prices->first();

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name,
            'status' => $product->lifecycleStatus(),
            'current_price' => $current ? (string) $current->price : null,
            'currency' => $current?->currency,
            'effective_date' => $current?->effective_date?->format('Y-m-d'),
            'has_price' => $current !== null,
        ];
    }

    private function exportCsv($products): StreamedResponse
    {
        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Product', 'SKU', 'Category', 'Status', 'Current Price', 'Currency', 'Effective Date']);
            foreach ($products as $product) {
                $row = $this->catalogRow($product);
                fputcsv($handle, [
                    $row['product_name'], $row['sku'], $row['category'], $row['status'],
                    $row['current_price'] ?? 'N/A', $row['currency'], $row['effective_date'],
                ]);
            }
            fclose($handle);
        }, 'product-price-catalog.csv', ['Content-Type' => 'text/csv']);
    }
}
