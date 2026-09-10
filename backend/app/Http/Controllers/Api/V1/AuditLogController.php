<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Tenant;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Full, unfiltered-by-default audit trail. Read-only — there is
     * deliberately no update/delete route for audit_logs anywhere in
     * the API.
     */
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user')
            ->where('business_id', Tenant::id())
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
