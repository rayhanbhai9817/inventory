<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'name', 'company_name', 'contact_person', 'email', 'phone',
    'address', 'city', 'state', 'country', 'website', 'tax_number', 'notes', 'status',
])]
class Supplier extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at')->where('status', 'active');
    }

    public function scopeNotArchived(Builder $query): void
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
        return $this->isArchived() ? 'archived' : $this->status;
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot(['supplier_sku', 'supplier_product_name', 'notes', 'is_primary', 'status'])
            ->withTimestamps();
    }
}
