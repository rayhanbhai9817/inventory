<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Writes immutable audit trail entries. Deliberately a plain insert with
 * no update/delete path exposed anywhere in the app — see AuditLogController
 * (read-only) and rule: "Audit records should not be editable through the UI."
 */
class AuditLogger
{
    public function log(string $action, ?Model $subject, ?array $old, ?array $new, ?User $user = null): AuditLog
    {
        return AuditLog::create([
            'business_id' => Tenant::id(),
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
