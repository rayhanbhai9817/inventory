<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierRequest;
use App\Http\Resources\StockBatchResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->string('tab', 'active')->toString();

        $query = Supplier::query()->withCount('supplierProducts');

        $query = match ($tab) {
            'archived' => $query->archived(),
            'inactive' => $query->notArchived()->where('status', 'inactive'),
            'all' => $query,
            default => $query->notArchived()->where('status', 'active'),
        };

        $suppliers = $query
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            return $this->exportCsv($suppliers->getCollection());
        }

        return SupplierResource::collection($suppliers);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        $this->auditLogger->log('supplier_created', $supplier, null, $supplier->only(['name', 'email']), $request->user());
        $this->notifications->supplierAdded($supplier);

        return response()->json(['data' => new SupplierResource($supplier)], 201);
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount('supplierProducts');

        $suppliedProducts = $supplier->supplierProducts()
            ->with('product')
            ->get()
            ->map(function ($link) use ($supplier) {
                $lastBatch = $supplier->batches()
                    ->where('product_id', $link->product_id)
                    ->orderByDesc('received_at')
                    ->first();
                $firstBatch = $supplier->batches()
                    ->where('product_id', $link->product_id)
                    ->orderBy('received_at')
                    ->first();

                return [
                    'product_id' => $link->product_id,
                    'product_name' => $link->product?->name,
                    'sku' => $link->product?->sku,
                    'supplier_sku' => $link->supplier_sku,
                    'supplier_product_name' => $link->supplier_product_name,
                    'is_primary' => $link->is_primary,
                    'status' => $link->status,
                    'first_supplied_at' => $firstBatch?->received_at,
                    'last_supplied_at' => $lastBatch?->received_at,
                ];
            });

        $recentStockIn = $supplier->batches()
            ->with(['product', 'creator'])
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'supplier' => new SupplierResource($supplier),
                'supplied_products' => $suppliedProducts,
                'totals' => [
                    'total_products_supplied' => $supplier->supplierProducts()->count(),
                    'total_stock_in_quantity' => (int) $supplier->batches()->sum('total_units'),
                ],
                'recent_stock_in' => StockBatchResource::collection($recentStockIn),
            ],
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $old = $supplier->only(['name', 'email', 'status']);
        $supplier->update($request->validated());

        $this->auditLogger->log('supplier_updated', $supplier, $old, $supplier->only(['name', 'email', 'status']), $request->user());

        return new SupplierResource($supplier);
    }

    public function archive(Supplier $supplier, Request $request): JsonResponse
    {
        $supplier->forceFill(['archived_at' => now()])->save();
        $this->auditLogger->log('supplier_archived', $supplier, null, null, $request->user());

        return response()->json(['message' => 'Supplier archived.']);
    }

    public function restore(Supplier $supplier, Request $request): JsonResponse
    {
        $supplier->forceFill(['archived_at' => null])->save();
        $this->auditLogger->log('supplier_restored', $supplier, null, null, $request->user());

        return response()->json(['message' => 'Supplier restored.']);
    }

    private function exportCsv($suppliers): StreamedResponse
    {
        return response()->streamDownload(function () use ($suppliers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Company', 'Contact Person', 'Email', 'Phone', 'Country', 'Status', 'Products Supplied']);
            foreach ($suppliers as $supplier) {
                fputcsv($handle, [
                    $supplier->name,
                    $supplier->company_name,
                    $supplier->contact_person,
                    $supplier->email,
                    $supplier->phone,
                    $supplier->country,
                    $supplier->lifecycleStatus(),
                    $supplier->supplier_products_count ?? 0,
                ]);
            }
            fclose($handle);
        }, 'suppliers.csv', ['Content-Type' => 'text/csv']);
    }
}
