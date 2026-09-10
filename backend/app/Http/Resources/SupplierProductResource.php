<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'supplier_sku' => $this->supplier_sku,
            'supplier_product_name' => $this->supplier_product_name,
            'notes' => $this->notes,
            'is_primary' => $this->is_primary,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
