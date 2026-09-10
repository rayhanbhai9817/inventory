<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierProductRequest;
use App\Http\Resources\SupplierProductResource;
use App\Models\SupplierProduct;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierProductController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $links = SupplierProduct::query()
            ->with(['supplier', 'product'])
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return SupplierProductResource::collection($links);
    }

    public function store(SupplierProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (SupplierProduct::where('supplier_id', $data['supplier_id'])->where('product_id', $data['product_id'])->exists()) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is already linked to this supplier.'],
            ]);
        }

        $link = DB::transaction(function () use ($data) {
            $link = SupplierProduct::create($data);

            if ($link->is_primary) {
                $this->clearOtherPrimaries($link);
            }

            return $link;
        });

        $this->auditLogger->log('supplier_product_linked', $link, null, $data, $request->user());

        return response()->json(['data' => new SupplierProductResource($link->load(['supplier', 'product']))], 201);
    }

    public function update(SupplierProductRequest $request, SupplierProduct $supplierProduct)
    {
        $old = $supplierProduct->only(['supplier_sku', 'is_primary', 'status']);

        DB::transaction(function () use ($request, $supplierProduct) {
            $supplierProduct->update($request->validated());

            if ($supplierProduct->is_primary) {
                $this->clearOtherPrimaries($supplierProduct);
            }
        });

        $this->auditLogger->log(
            'supplier_product_updated',
            $supplierProduct,
            $old,
            $supplierProduct->only(['supplier_sku', 'is_primary', 'status']),
            $request->user(),
        );

        return new SupplierProductResource($supplierProduct->load(['supplier', 'product']));
    }

    public function destroy(SupplierProduct $supplierProduct, Request $request): JsonResponse
    {
        $this->auditLogger->log('supplier_product_unlinked', $supplierProduct, $supplierProduct->only(['supplier_id', 'product_id']), null, $request->user());
        $supplierProduct->delete();

        return response()->json(['message' => 'Supplier-product link removed.']);
    }

    private function clearOtherPrimaries(SupplierProduct $link): void
    {
        SupplierProduct::where('product_id', $link->product_id)
            ->where('id', '!=', $link->id)
            ->update(['is_primary' => false]);
    }
}
