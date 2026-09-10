<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Tenant;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * "Daily Activity" — an operational, date-scoped view over the audit
     * trail (defaults to today). See AuditLogController for the
     * unfiltered, admin-only audit log.
     */
    public function index(Request $request)
    {
        $from = $request->date('date') ? $request->date('date')->startOfDay() : now()->startOfDay();
        $to = (clone $from)->endOfDay();

        $activity = AuditLog::query()
            ->with('user')
            ->where('business_id', Tenant::id())
            ->whereBetween('created_at', [$from, $to])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->string('module')->toString(), fn ($q, $module) => $q->where('auditable_type', 'like', "%{$module}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($activity);
    }
}
