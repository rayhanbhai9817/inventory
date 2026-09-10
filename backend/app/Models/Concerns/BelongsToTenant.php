<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Enforces row-level tenant isolation server-side. Every model using this
 * trait is automatically scoped to the current tenant (App\Support\Tenant)
 * on every query, and new records are automatically stamped with the
 * current tenant's business_id. This is the backend's authoritative
 * boundary against cross-tenant data access (IDOR) — the frontend is
 * never trusted to filter by business.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $column = $builder->getModel()->getTable().'.business_id';

            if (Tenant::check()) {
                $builder->where($column, Tenant::id());

                return;
            }

            // No tenant context resolved: fail closed. Returning unscoped
            // rows here would leak every tenant's data to whichever code
            // path forgot to set one. Code that legitimately needs
            // cross-tenant access (console commands, admin tooling) must
            // opt in explicitly via Model::withoutGlobalScope('tenant').
            $builder->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            if (! $model->business_id) {
                if (! Tenant::check()) {
                    throw new RuntimeException(
                        static::class.' requires a tenant context to create a record. '.
                        'Set App\Support\Tenant::set($businessId) or pass business_id explicitly.'
                    );
                }

                $model->business_id = Tenant::id();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
