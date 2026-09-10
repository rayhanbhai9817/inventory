<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Administrative reference price. Deliberately NOT read by
 * InventoryService, StockBatch, or anything in the FIFO engine — see the
 * "Price vs. inventory separation" section of docs/ARCHITECTURE.md.
 */
#[Fillable(['business_id', 'product_id', 'price', 'currency', 'effective_date', 'notes', 'created_by'])]
class ProductPrice extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
