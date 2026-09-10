<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockLedgerController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filtered($request);

        if ($request->boolean('export') && $request->string('format') === 'csv') {
            return $this->exportCsv($query->get());
        }

        $movements = $query->paginate($request->integer('per_page', 25));

        return StockMovementResource::collection($movements);
    }

    private function filtered(Request $request)
    {
        return StockMovement::query()
            ->with(['product', 'user', 'batchLinks.batch'])
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('batch_code')->toString(), fn ($q, $code) => $q->whereHas(
                'batchLinks.batch',
                fn ($q) => $q->where('batch_code', $code)
            ))
            ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->orderByDesc('created_at');
    }

    private function exportCsv($movements): StreamedResponse
    {
        return Response::streamDownload(function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Reference', 'Product', 'SKU', 'Type', 'Units', 'Balance After', 'User', 'Note']);
            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->created_at,
                    $m->reference(),
                    $m->product?->name,
                    $m->product?->sku,
                    $m->type,
                    $m->units,
                    $m->balance_after,
                    $m->user?->name,
                    $m->note,
                ]);
            }
            fclose($handle);
        }, 'stock-ledger.csv', ['Content-Type' => 'text/csv']);
    }
}
