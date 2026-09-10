<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'type' => $this->type,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'units' => $this->units,
            'balance_after' => $this->balance_after,
            'note' => $this->note,
            'batches' => $this->whenLoaded('batchLinks', fn () => $this->batchLinks->map(fn ($link) => [
                'batch_code' => $link->batch?->batch_code,
                'units' => $link->units,
            ])),
            'user' => $this->whenLoaded('user', fn () => $this->user->name),
            'created_at' => $this->created_at,
        ];
    }
}
