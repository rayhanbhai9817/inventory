<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id', 'category_id', 'name', 'sku', 'description',
    'image_path', 'min_stock_level', 'created_by',
])]
class Product extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function lifecycleStatus(): string
    {
        if ($this->trashed()) {
            return 'trashed';
        }

        return $this->isArchived() ? 'archived' : 'active';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot(['supplier_sku', 'supplier_product_name', 'notes', 'is_primary', 'status'])
            ->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Latest reference price by effective_date — "current price" is
     * derived, never cached, so it can never drift from the history it's
     * drawn from. Purely administrative reference data; never used by
     * the inventory engine.
     */
    public function currentPrice(): ?ProductPrice
    {
        return $this->prices()->orderByDesc('effective_date')->orderByDesc('id')->first();
    }

    /**
     * Live aggregate over batches — deliberately not cached/denormalized,
     * since a stale cached total is worse than one extra SUM() query for
     * inventory numbers (see docs/ARCHITECTURE.md).
     */
    public function totalRemainingUnits(): int
    {
        return (int) $this->batches()->where('status', '!=', 'depleted')->sum('remaining_units');
    }

    public function totalBoxes(): float
    {
        $batches = $this->batches()->where('status', '!=', 'depleted')->get(['units_per_box', 'remaining_units']);

        return round($batches->sum(fn ($b) => $b->remaining_units / max($b->units_per_box, 1)), 2);
    }
}
