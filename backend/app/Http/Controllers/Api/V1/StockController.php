<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockInRequest;
use App\Http\Requests\Stock\StockOutRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function in(StockInRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);

        $movement = $this->inventory->stockIn(
            $product,
            $data['boxes'],
            $data['units_per_box'],
            $data['received_at'],
            $data['notes'] ?? null,
            $request->user(),
        );

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function out(StockOutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);

        try {
            $movement = $this->inventory->stockOut(
                $product,
                $data['units'],
                $data['notes'] ?? null,
                $request->user(),
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }
}
