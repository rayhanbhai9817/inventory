<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name'])]
class ExpenseCategory extends Model
{
    use BelongsToTenant;

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
