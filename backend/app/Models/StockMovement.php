<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['business_id', 'product_id', 'type', 'units', 'balance_after', 'note', 'user_id'])]
class StockMovement extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batchLinks(): HasMany
    {
        return $this->hasMany(StockMovementBatch::class);
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class);
    }

    /**
     * Human-readable reference (e.g. "SI-000042"). Derived from the
     * immutable primary key rather than a separately tracked counter, so
     * it can never collide or drift out of sync.
     */
    public function reference(): string
    {
        $prefix = match ($this->type) {
            'stock_in' => 'SI',
            'stock_out' => 'SO',
            'adjustment_increase', 'adjustment_decrease' => 'ADJ',
            default => 'MV',
        };

        return sprintf('%s-%06d', $prefix, $this->id);
    }
}
