<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin, read-only aggregate reports over Suppliers / SupplierProducts /
 * StockBatches. No new business logic — every number here is derived
 * from data already written by the Supplier and Inventory modules.
 */
class SupplierReportController extends Controller
{
    /**
     * One row per supplier: products supplied, total stock-in quantity,
     * batch count, last stock-in date.
     */
    public function suppliers(Request $request)
    {
        $suppliers = Supplier::query()
            ->notArchived()
            ->withCount(['supplierProducts', 'batches'])
            ->withSum('batches as total_units_supplied', 'total_units')
            ->withMax('batches as last_stock_in_at', 'received_at')
            ->orderBy('name')
            ->get();

        $rows = $suppliers->map(fn (Supplier $supplier) => [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'status' => $supplier->lifecycleStatus(),
            'products_supplied' => $supplier->supplier_products_count,
            'batches_received' => $supplier->batches_count,
            'total_units_supplied' => (int) ($supplier->total_units_supplied ?? 0),
            'last_stock_in_at' => $supplier->last_stock_in_at,
        ]);

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            abort_unless($request->user()->can('supplier_reports.export'), 403);

            return $this->exportSuppliersCsv($rows);
        }

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * One row per product: which suppliers it's linked to, its primary
     * supplier, and whether it has none at all (the spec's "products
     * missing a supplier" alert, in report form).
     */
    public function productSuppliers(Request $request)
    {
        $products = Product::query()
            ->active()
            ->with(['category', 'suppliers'])
            ->when($request->boolean('missing_supplier'), fn ($q) => $q->doesntHave('suppliers'))
            ->orderBy('name')
            ->get();

        $rows = $products->map(function (Product $product) {
            $primary = $product->suppliers->firstWhere('pivot.is_primary', true);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'primary_supplier' => $primary?->name,
                'supplier_count' => $product->suppliers->count(),
                'suppliers' => $product->suppliers->pluck('name')->values(),
                'has_supplier' => $product->suppliers->isNotEmpty(),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    private function exportSuppliersCsv($rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Supplier', 'Status', 'Products Supplied', 'Batches Received', 'Total Units Supplied', 'Last Stock In']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['supplier_name'], $row['status'], $row['products_supplied'],
                    $row['batches_received'], $row['total_units_supplied'], $row['last_stock_in_at'],
                ]);
            }
            fclose($handle);
        }, 'supplier-report.csv', ['Content-Type' => 'text/csv']);
    }
}
