<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's tenant (business) and binds it for the
 * remainder of the request: App\Support\Tenant (used by the
 * BelongsToTenant model scope) and Spatie's permission "team" id (used to
 * scope role/permission checks to the current business). Must run after
 * auth:sanctum.
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->business_id) {
            return response()->json([
                'message' => 'No business associated with this account.',
            ], 403);
        }

        Tenant::set($user->business_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->business_id);

        return $next($request);
    }
}
