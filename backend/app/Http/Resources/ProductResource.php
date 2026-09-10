<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'short_name' => $this->unit->short_name,
            ]),
            'cost_price' => (float) $this->cost_price,
            'selling_price' => (float) $this->selling_price,
            'min_stock_level' => (float) $this->min_stock_level,
            'total_stock' => $this->when(
                $this->relationLoaded('stocks'),
                fn () => (float) $this->stocks->sum('quantity')
            ),
            'stocks' => WarehouseStockResource::collection($this->whenLoaded('stocks')),
            'description' => $this->description,
            'image_path' => $this->image_path,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
