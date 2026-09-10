<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->string('tab', 'active')->toString();

        $query = Product::query()->with(['category', 'batches']);

        $query = match ($tab) {
            'archived' => $query->archived(),
            'trashed' => $query->onlyTrashed(),
            default => $query->active(),
        };

        $products = $query
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($request->integer('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            return $this->exportCsv($products->getCollection());
        }

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $product = Product::create($data);

        $this->auditLogger->log('product_created', $product, null, $product->only(['name', 'sku', 'category_id']), $request->user());

        return new ProductResource($product->load(['category', 'batches']));
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'batches' => fn ($q) => $q->orderByDesc('received_at')]));
    }

    public function update(ProductRequest $request, Product $product)
    {
        $old = $product->only(['name', 'sku', 'category_id', 'description', 'min_stock_level']);
        $product->update($request->validated());

        $this->auditLogger->log(
            'product_updated',
            $product,
            $old,
            $product->only(['name', 'sku', 'category_id', 'description', 'min_stock_level']),
            $request->user(),
        );

        return new ProductResource($product->load(['category', 'batches']));
    }

    public function archive(Product $product, Request $request): JsonResponse
    {
        $product->forceFill(['archived_at' => now()])->save();
        $this->auditLogger->log('product_archived', $product, null, null, $request->user());
        $this->notifications->adminActivity($product, "{$product->name} was archived.");

        return response()->json(['message' => 'Product archived.']);
    }

    public function restore(Product $product, Request $request): JsonResponse
    {
        $product->forceFill(['archived_at' => null])->save();
        $this->auditLogger->log('product_restored', $product, null, null, $request->user());
        $this->notifications->adminActivity($product, "{$product->name} was restored from archive.");

        return response()->json(['message' => 'Product restored.']);
    }

    /** Move to Trashed (soft delete). */
    public function destroy(Product $product, Request $request): JsonResponse
    {
        $product->delete();
        $this->auditLogger->log('product_trashed', $product, null, null, $request->user());
        $this->notifications->adminActivity($product, "{$product->name} was moved to trash.");

        return response()->json(['message' => 'Product moved to trash.']);
    }

    /** Restore from Trashed back to Active. */
    public function restoreFromTrash(int $productId, Request $request): JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($productId);
        $product->restore();
        $product->forceFill(['archived_at' => null])->save();
        $this->auditLogger->log('product_restored_from_trash', $product, null, null, $request->user());
        $this->notifications->adminActivity($product, "{$product->name} was restored from trash.");

        return response()->json(['message' => 'Product restored.']);
    }

    private function exportCsv($products): StreamedResponse
    {
        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Category', 'Description', 'Total Boxes', 'Total Units', 'Status']);
            foreach ($products as $product) {
                $resource = (new ProductResource($product))->toArray(request());
                fputcsv($handle, [
                    $resource['sku'],
                    $resource['name'],
                    $resource['category']['name'] ?? '',
                    $resource['description'],
                    $resource['total_boxes'],
                    $resource['total_units'],
                    $resource['status'],
                ]);
            }
            fclose($handle);
        }, 'products.csv', ['Content-Type' => 'text/csv']);
    }
}
