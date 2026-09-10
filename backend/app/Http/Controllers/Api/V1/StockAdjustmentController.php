<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request)
    {
        $adjustments = StockAdjustment::query()
            ->with(['product', 'user'])
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return StockAdjustmentResource::collection($adjustments);
    }

    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);

        try {
            $adjustment = $this->inventory->adjust(
                $product,
                $data['direction'],
                $data['quantity'],
                $data['reason'],
                $data['note'] ?? null,
                $request->user(),
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new StockAdjustmentResource($adjustment)], 201);
    }
}
