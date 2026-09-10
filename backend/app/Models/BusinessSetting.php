<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'date_format', 'number_format', 'default_min_stock_threshold',
    'low_stock_notifications_enabled', 'out_of_stock_notifications_enabled',
])]
class BusinessSetting extends Model
{
    protected function casts(): array
    {
        return [
            'low_stock_notifications_enabled' => 'boolean',
            'out_of_stock_notifications_enabled' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
