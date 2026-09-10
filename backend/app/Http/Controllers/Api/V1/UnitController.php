<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::query()
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UnitResource::collection($units);
    }

    public function store(UnitRequest $request)
    {
        return new UnitResource(Unit::create($request->validated()));
    }

    public function show(Unit $unit)
    {
        return new UnitResource($unit);
    }

    public function update(UnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a unit that is assigned to products.',
            ], 409);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit deleted.']);
    }
}
