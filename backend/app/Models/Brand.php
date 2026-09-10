<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['business_id', 'name', 'slug', 'status'])]
class Brand extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
