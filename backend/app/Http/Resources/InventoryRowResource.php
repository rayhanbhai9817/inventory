<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeBatches = $this->batches->where('status', '!=', 'depleted');
        $totalUnits = (int) $activeBatches->sum('remaining_units');
        $totalBoxes = round($activeBatches->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1)), 2);
        $distinctRatios = $activeBatches->pluck('units_per_box')->unique();

        $status = match (true) {
            $totalUnits <= 0 => 'out_of_stock',
            $totalUnits <= $this->min_stock_level && $this->min_stock_level > 0 => 'low_stock',
            default => 'in_stock',
        };

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'boxes' => $totalBoxes,
            'units_per_box' => $distinctRatios->count() === 1 ? $distinctRatios->first() : null,
            'total_units' => $totalUnits,
            'min_stock_level' => $this->min_stock_level,
            'stock_in_total' => (int) ($this->total_stock_in ?? 0),
            'stock_out_total' => (int) ($this->total_stock_out ?? 0),
            'status' => $status,
        ];
    }
}
