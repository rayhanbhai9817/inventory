<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockBatchResource;
use App\Models\StockBatch;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = StockBatch::query()
            ->with(['product', 'creator'])
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('batch_code', 'like', "%{$search}%"))
            ->orderByDesc('received_at')
            ->paginate($request->integer('per_page', 15));

        return StockBatchResource::collection($batches);
    }

    public function show(StockBatch $batch)
    {
        return new StockBatchResource($batch->load(['product', 'creator']));
    }
}
