<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'product_id', 'supplier_id', 'batch_code', 'boxes', 'units_per_box',
    'total_units', 'remaining_units', 'status', 'received_at', 'notes', 'created_by',
])]
class StockBatch extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movementLinks(): HasMany
    {
        return $this->hasMany(StockMovementBatch::class);
    }

    public function remainingBoxes(): float
    {
        return round($this->remaining_units / max($this->units_per_box, 1), 2);
    }

    public function refreshStatus(): void
    {
        $this->status = match (true) {
            $this->remaining_units <= 0 => 'depleted',
            $this->remaining_units < $this->total_units => 'partial',
            default => 'full',
        };
    }
}
