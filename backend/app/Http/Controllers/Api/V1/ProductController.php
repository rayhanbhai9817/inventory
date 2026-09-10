<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category', 'brand', 'unit', 'stocks'])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->when($request->integer('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->integer('brand_id'), fn ($q, $brandId) => $q->where('brand_id', $brandId))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereRaw(
                '(select coalesce(sum(quantity), 0) from product_stocks where product_stocks.product_id = products.id) <= products.min_stock_level'
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->validated());

            // Ensure every warehouse has a zero-quantity stock row so
            // stock lookups never have to special-case "no row yet".
            $warehouses = Warehouse::query()->pluck('id');
            foreach ($warehouses as $warehouseId) {
                $product->stocks()->create([
                    'business_id' => $product->business_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                ]);
            }

            return $product;
        });

        return new ProductResource($product->load(['category', 'brand', 'unit', 'stocks']));
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'brand', 'unit', 'stocks.warehouse']));
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product->load(['category', 'brand', 'unit', 'stocks']));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
