<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Deliberately NOT tenant-scoped via BelongsToTenant: audit events can be
 * written without an active tenant context (e.g. a failed login before a
 * business is resolved), so business_id stays nullable and is set
 * explicitly by the caller rather than auto-stamped. Any endpoint that
 * lists audit logs must filter by business_id itself.
 */
#[Fillable([
    'business_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
