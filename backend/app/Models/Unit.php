<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'short_name'])]
class Unit extends Model
{
    use BelongsToTenant, HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
