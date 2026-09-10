<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Computed in PHP over an eager-loaded `batches` relation (loaded
        // once per page via a single IN(...) query by the controller) —
        // deliberately not a live per-row aggregate query, to avoid N+1.
        $activeBatches = $this->whenLoaded('batches', fn () => $this->batches->where('status', '!=', 'depleted'));

        $totalUnits = null;
        $totalBoxes = null;
        $unitsPerBox = null;

        if ($activeBatches !== null) {
            $totalUnits = (int) $activeBatches->sum('remaining_units');
            $totalBoxes = round($activeBatches->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1)), 2);
            $distinctRatios = $activeBatches->pluck('units_per_box')->unique();
            $unitsPerBox = $distinctRatios->count() === 1 ? $distinctRatios->first() : null;
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'min_stock_level' => $this->min_stock_level,
            'total_units' => $totalUnits,
            'total_boxes' => $totalBoxes,
            'units_per_box' => $unitsPerBox,
            'status' => $this->lifecycleStatus(),
            'archived_at' => $this->archived_at,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
        ];
    }
}
