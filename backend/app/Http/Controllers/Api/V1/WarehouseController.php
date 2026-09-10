<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::query()
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return WarehouseResource::collection($warehouses);
    }

    public function store(WarehouseRequest $request)
    {
        $warehouse = DB::transaction(function () use ($request) {
            $warehouse = Warehouse::create($request->validated());

            if ($warehouse->is_default) {
                Warehouse::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }

            return $warehouse;
        });

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        DB::transaction(function () use ($request, $warehouse) {
            $warehouse->update($request->validated());

            if ($warehouse->is_default) {
                Warehouse::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }
        });

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->productStocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a warehouse that still holds stock.',
            ], 409);
        }

        $warehouse->delete();

        return response()->json(['message' => 'Warehouse deleted.']);
    }
}
